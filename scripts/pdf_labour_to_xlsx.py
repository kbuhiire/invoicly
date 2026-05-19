#!/usr/bin/env python3
"""
Convert scanned labour roster PDFs to a single-sheet Excel file.

Tries PyMuPDF text first; if pages are empty, uses RapidOCR (ONNX). Pass `--tesseract`
to prefer Homebrew Tesseract via PyMuPDF when installed (falls back to RapidOCR).

Usage:
  . .venv-pdf/bin/activate   # after: python3 -m venv .venv-pdf && pip install -r scripts/pdf-labour-requirements.txt
  python3 scripts/pdf_labour_to_xlsx.py -i "/path/to/General 24th April.pdf" -o ~/Downloads/General_24th_April.xlsx
"""

from __future__ import annotations

import argparse
import os
import re
import shutil
import sys
from pathlib import Path
from typing import Callable, Iterable, List, Optional, Tuple

import fitz
import numpy as np
import pandas as pd
from rapidocr_onnxruntime import RapidOCR


def cluster_lines_from_ocr_result(result: list) -> List[str]:
    if not result:
        return []
    entries = []
    for box, text, _score in result:
        ys = [p[1] for p in box]
        xs = [p[0] for p in box]
        h = max(ys) - min(ys)
        entries.append((min(ys), min(xs), h, text.strip()))
    heights = sorted([e[2] for e in entries])
    median_h = heights[len(heights) // 2] if heights else 12
    y_thresh = max(median_h * 0.55, 10)
    entries.sort(key=lambda e: (e[0], e[1]))
    lines_out: List[str] = []
    cur = [entries[0]]
    ref_y = entries[0][0]
    for e in entries[1:]:
        if abs(e[0] - ref_y) <= y_thresh:
            cur.append(e)
        else:
            cur.sort(key=lambda x: x[1])
            lines_out.append(" ".join(x[3] for x in cur))
            cur = [e]
            ref_y = e[0]
    cur.sort(key=lambda x: x[1])
    lines_out.append(" ".join(x[3] for x in cur))
    return lines_out


def ocr_page_lines(page: fitz.Page, ocr: RapidOCR, zoom: float = 2.5) -> List[str]:
    mat = fitz.Matrix(zoom, zoom)
    pix = page.get_pixmap(matrix=mat, alpha=False)
    img = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width, pix.n)
    if pix.n == 4:
        img = img[:, :, :3]
    result, _ = ocr(img)
    return cluster_lines_from_ocr_result(result or [])


def ocr_page_lines_tesseract(page: fitz.Page, dpi: int = 200) -> List[str]:
    tp = page.get_textpage_ocr(dpi=dpi, language="eng", full=True)
    text = page.get_text("text", textpage=tp)
    return [ln.strip() for ln in text.splitlines() if ln.strip()]


def find_tesseract() -> Optional[str]:
    for bindir in ("/opt/homebrew/bin", "/usr/local/bin"):
        p = Path(bindir) / "tesseract"
        if p.is_file():
            return str(p)
    return shutil.which("tesseract")


def norm_alnum(s: str) -> str:
    return re.sub(r"[^a-z0-9]+", "", s.lower())


def extract_date_string(line: str) -> Optional[str]:
    """Return ISO YYYY-MM-DD when parseable, else None."""
    line_clean = re.sub(r"\s+", " ", line)
    # 14- 04-2 026, 19-04-202, MoN 204/04 h2026
    m = re.search(
        r"(\d{1,2})\s*[-hH/.:yY]+\s*(\d{1,2})\s*[-/.:yY]+\s*(\d{2,4})",
        line_clean,
    )
    if m:
        d, mo, y = m.groups()
        yi = int(y)
        if yi < 100:
            yi += 2000
        if yi == 202:
            yi = 2026
        if 1 <= int(d) <= 31 and 1 <= int(mo) <= 12:
            try:
                return f"{yi:04d}-{int(mo):02d}-{int(d):02d}"
            except ValueError:
                pass
    # SAT 18 oy 2026
    m2 = re.search(
        r"(?:mon|tue|wed|thu|fri|sat|sun)[a-z]*\s+(\d{1,2})\D+(\d{4})",
        line_clean,
        re.I,
    )
    if m2:
        d, y = m2.groups()
        yi = int(y)
        if yi < 100:
            yi += 2000
        return f"{yi:04d}-04-{int(d):02d}"
    return None


def extract_date_raw_fragment(line: str) -> Optional[str]:
    """Keep human-readable fragment when ISO parse fails."""
    m = re.search(
        r"(\d{1,2}\s*[-hH/.:]+\s*\d{1,2}\s*[-/.:]+\s*\d{2,4})",
        line,
    )
    if m:
        return re.sub(r"\s+", "", m.group(1))
    return None


def classify_category(blob: str) -> Optional[str]:
    """Map OCR-noisy header text to one of four categories."""
    n = norm_alnum(blob)
    if "pipe" in n and "team" in n:
        return "Pipe team"
    if "clean" in n or "cean" in n or "claners" in n or "clangers" in n:
        return "Cleaners"
    # Friday / night shift + general labour
    if ("frid" in n or "friday" in n) and ("general" in n or "lab" in n):
        return "Friday Night Shift General Labours"
    if "night" in n and "shift" in n and ("general" in n or "lab" in n or "gene" in n):
        return "Friday Night Shift General Labours"
    if ("nigh" in n or "nuht" in n) and "shift" in n and ("lab" in n or "gene" in n):
        return "Friday Night Shift General Labours"
    if "shitcgneeal" in n or "shitcgeneral" in n:  # NUHT SHITCGNEEAL LAB
        return "Friday Night Shift General Labours"
    if "general" in n and "lab" in n:
        return "General Labours"
    if "gene" in n and "lab" in n:
        return "General Labours"
    if "labour" in n or "labou" in n:
        if "general" in n or "gene" in n or "genel" in n or "genral" in n:
            return "General Labours"
    return None


def is_noise_line(line: str) -> bool:
    ln = line.lower().replace(" ", "")
    if "camscanner" in ln or "scannedwith" in ln:
        return True
    return False


def is_work_log_line(line: str) -> bool:
    """Heuristic: site activity lines (not name roster)."""
    if len(line) > 130:
        return True
    low = line.lower()
    keys = (
        "fom ",
        " from ",
        "floor",
        "ferry",
        "femy",
        "ferm",
        "cement",
        "mixer",
        "bags",
        "aggregate",
        "slab",
        "pple ",
        "p/pl",
        "timber",
        "boards",
        "offload",
        "cleared the way",
        "collect",
        "wheel",
        "crane",
        "boam",
        "beam",
        "pcs",
    )
    if any(k in low for k in keys):
        return True
    if re.search(r'\d+\s*\(\s*4["\']?', line):
        return True
    if re.search(r"\d+\s*p['/`]?pl", low):
        return True
    return False


def is_entry_line(line: str) -> bool:
    if not line.strip() or is_noise_line(line):
        return False
    if is_work_log_line(line):
        return False
    s = line.strip()
    # Numbered roster: 1) foo, 3)bar, 12/ name, 1Onyengg (digit glued)
    if re.match(r"^\d{1,2}\s*[\)\]./\-–—]", s):
        return True
    if re.match(r"^\d{1,2}[\)\]./\-–—][^\d]", s):
        return True
    if re.match(r"^\d{1,2}[A-Za-z(]", s):
        return True
    if re.match(r"^\(?\d{1,2}\s*[\)\]/]", s):
        return True
    if len(s) <= 48 and re.search(r"[A-Za-z]{3,}", s) and not re.search(r"\d{4}", s):
        return True
    return False


def iter_pdf_lines(
    pdf_path: Path,
    ocr_getter: Callable[[], RapidOCR],
    zoom: float,
    *,
    try_tesseract_first: bool = False,
    tess_dpi: int = 200,
) -> Iterable[Tuple[int, str]]:
    doc = fitz.open(pdf_path)
    try:
        for pi in range(len(doc)):
            page = doc[pi]
            text = page.get_text("text") or ""
            lines = [ln.strip() for ln in text.splitlines() if ln.strip()]
            joined = "".join(lines)
            if len(joined) < 30:
                tess_lines: List[str] = []
                if try_tesseract_first:
                    try:
                        tess_lines = ocr_page_lines_tesseract(page, dpi=tess_dpi)
                    except RuntimeError:
                        tess_lines = []
                if len("".join(tess_lines)) >= 30:
                    lines = tess_lines
                else:
                    lines = ocr_page_lines(page, ocr_getter(), zoom=zoom)
            for ln in lines:
                yield (pi + 1, ln)
    finally:
        doc.close()


def parse_records(lines_with_page: List[Tuple[int, str]]) -> List[dict]:
    current_category: Optional[str] = None
    current_date: Optional[str] = None
    pending_friday: bool = False
    records: List[dict] = []
    recent: List[str] = []

    def window_blob(extra: str = "") -> str:
        parts = recent[-4:] + ([extra] if extra else [])
        return " ".join(parts)

    for page, line in lines_with_page:
        raw = line.strip()
        if is_noise_line(raw):
            continue
        recent.append(raw)
        recent[:] = recent[-6:]

        blob = window_blob(raw)
        cat = classify_category(blob) or classify_category(raw)
        if cat == "General Labours" and ("frid" in norm_alnum(blob) or pending_friday):
            cat = "Friday Night Shift General Labours"
            pending_friday = False
        if cat:
            current_category = cat
            if cat != "Friday Night Shift General Labours":
                pending_friday = False
            ds = extract_date_string(blob) or extract_date_string(raw)
            if ds:
                current_date = ds
            elif norm_alnum(raw).startswith("frid") or ("frid" in norm_alnum(raw) and "general" not in norm_alnum(raw)):
                pending_friday = True
            continue

        if pending_friday and classify_category(raw) == "General Labours":
            current_category = "Friday Night Shift General Labours"
            pending_friday = False
            ds = extract_date_string(raw) or extract_date_string(blob)
            if ds:
                current_date = ds
            continue

        if pending_friday and ("general" in norm_alnum(raw) or "gene" in norm_alnum(raw)):
            current_category = "Friday Night Shift General Labours"
            pending_friday = False
            ds = extract_date_string(blob) or extract_date_string(raw)
            if ds:
                current_date = ds
            continue

        if norm_alnum(raw).startswith("frid") or re.search(r"frid[a-z]*\s+\d", raw, re.I):
            pending_friday = True
            ds = extract_date_string(raw)
            if ds:
                current_date = ds
            continue

        ds = extract_date_string(raw)
        if ds and not is_entry_line(raw):
            current_date = ds
            continue

        if current_category and is_entry_line(raw):
            date_val = current_date or extract_date_string(raw)
            if not date_val:
                date_val = extract_date_raw_fragment(raw) or ""
            records.append(
                {
                    "Category": current_category,
                    "Date": date_val,
                    "Detail": raw,
                    "SourcePage": page,
                }
            )
    return records


def main() -> int:
    ap = argparse.ArgumentParser(description="PDF labour rosters → Excel (single sheet).")
    ap.add_argument(
        "-i",
        "--input",
        type=Path,
        default=Path.home() / "Downloads" / "General 24th April.pdf",
        help="Input PDF path",
    )
    ap.add_argument(
        "-o",
        "--output",
        type=Path,
        default=Path.home() / "Downloads" / "General_24th_April.xlsx",
        help="Output .xlsx path",
    )
    ap.add_argument("--zoom", type=float, default=2.5, help="RapidOCR render zoom (default 2.5)")
    ap.add_argument(
        "--tesseract-dpi",
        type=int,
        default=200,
        help="DPI when using Tesseract via PyMuPDF (default 200)",
    )
    ap.add_argument(
        "--tesseract",
        action="store_true",
        help="Prefer Tesseract (PyMuPDF OCR) when installed; falls back to RapidOCR per page if weak",
    )
    args = ap.parse_args()

    if not args.input.is_file():
        print(f"Input not found: {args.input}", file=sys.stderr)
        return 1

    tess = find_tesseract()
    if tess and args.tesseract:
        os.environ["PATH"] = str(Path(tess).parent) + os.pathsep + os.environ.get("PATH", "")

    ocr_holder: List[Optional[RapidOCR]] = [None]

    def ocr_getter() -> RapidOCR:
        if ocr_holder[0] is None:
            ocr_holder[0] = RapidOCR()
        return ocr_holder[0]

    try_tesseract = bool(tess) and args.tesseract
    lines_with_page = list(
        iter_pdf_lines(
            args.input,
            ocr_getter,
            args.zoom,
            try_tesseract_first=try_tesseract,
            tess_dpi=args.tesseract_dpi,
        )
    )
    records = parse_records(lines_with_page)
    if not records:
        print("No data rows extracted; tune header/entry heuristics in script.", file=sys.stderr)
        return 2

    df = pd.DataFrame.from_records(records)
    cols = ["Category", "Date", "Detail", "SourcePage"]
    df = df[[c for c in cols if c in df.columns]]
    args.output.parent.mkdir(parents=True, exist_ok=True)
    df.to_excel(args.output, index=False, sheet_name="Labour")
    print(f"Wrote {len(df)} rows to {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
