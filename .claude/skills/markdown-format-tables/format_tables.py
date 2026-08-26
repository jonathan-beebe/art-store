#!/usr/bin/env python3
"""Format markdown tables for plain-text readability.

Rules (see CLAUDE.md "Markdown Table Formatting"):

- Pad every column so the pipes align in plain text.
- Keep the whole table within a width budget (default 150 characters).
- A cell that will not fit wraps onto continuation rows — extra physical
  ``|`` rows with the other columns left empty — rather than moving content
  out of the table. Markdown links and inline code spans are never split
  across rows.
- Fenced code blocks pass through untouched.

Formatting is idempotent: continuation rows are re-joined into logical rows
before measuring, so re-running (or re-running with a different width)
re-flows tables instead of compounding old wraps. Wrapping only ever
happens when a table is squeezed to the width budget, so a table whose
separator row spans less than the budget cannot contain continuation rows
and is parsed row-for-row. In a budget-wide table, a continuation is
recognized by the greedy-wrap invariant — none of the row's cells' first
tokens would have fit at the end of the previous physical line within the
separator's column widths — which covers wraps in any column, the first
included. Without a separator row, only the empty-first-cell shape is
recognized.

Known limitations, deliberate:

- Cell width is ``len()`` — double-width characters (CJK, some emoji)
  will misalign in terminals.
- In a budget-wide table, a hand-written row that satisfies the
  continuation test (every cell full to the brim, the next row's first
  tokens too long to fit) merges into the row above.
- Re-running with a smaller ``--max-width`` than the one that produced the
  file re-flows correctly; a larger one can put a previously squeezed
  table under the gate and freeze its old wraps as rows.
- One table is expected to be one uninterrupted run of ``|`` lines, and its
  separator is the first all-dash row after the first physical row.

Usage: format_tables.py [--check] [--max-width N] FILE [FILE...]
"""
from __future__ import annotations

import argparse
import re
import sys
from dataclasses import dataclass, field
from pathlib import Path

DEFAULT_MAX_WIDTH = 150
MIN_COLUMN_WIDTH = 12  # never squeeze a column narrower than this

LINK = r"\[[^\]]*\]\([^)]*\)"
REF_LINK = r"\[[^\]]*\]\[[^\]]*\]"
CODE = r"`[^`]+`"
# A token is either a whitespace-delimited run containing markdown links or
# inline code spans (the atoms plus anything glued to them stay atomic, a
# code span's internal spaces included) or a plain word. The glue around an
# atom excludes backticks so a span always opens at the run's first backtick
# and closes at the next one — greedy glue could otherwise swallow an
# opening backtick and pair the remaining ones across the cell.
TOKEN_RE = re.compile(rf"(?:[^\s`]*(?:{LINK}|{REF_LINK}|{CODE}))+[^\s`]*|\S+")
SEPARATOR_CELL_RE = re.compile(r":?-{3,}:?")
FENCE_RE = re.compile(r"^\s*(`{3,}|~{3,})")


@dataclass(frozen=True)
class Table:
    """A markdown table as logical content: continuation rows already joined,
    alignment held as data rather than dash-and-colon cell text."""

    header_rows: list[list[str]] = field(default_factory=list)
    alignments: list[str] | None = None  # None = table has no separator row
    body_rows: list[list[str]] = field(default_factory=list)

    @property
    def columns(self) -> int:
        return max(len(row) for row in self.header_rows + self.body_rows)

    @property
    def logical_rows(self) -> list[list[str]]:
        return self.header_rows + self.body_rows


# ── Parsing ──────────────────────────────────────────────────────────────


def split_row(line: str) -> list[str]:
    """Split a table row into cells on unescaped pipes, dropping exactly one
    leading/trailing pipe artifact — an intentionally empty last cell
    (``| a | b | |`` or ``| a | b ||``) survives."""
    stripped = line.strip()
    cells = re.split(r"(?<!\\)\|", stripped)
    if cells and stripped.startswith("|"):
        cells = cells[1:]
    if cells and stripped.endswith("|") and not stripped.endswith("\\|"):
        cells = cells[:-1]
    return [cell.strip() for cell in cells]


def is_sep_cell(cell: str) -> bool:
    return SEPARATOR_CELL_RE.fullmatch(cell) is not None


def is_separator_row(row: list[str]) -> bool:
    """A markdown separator row: every cell is dashes (with optional alignment
    colons); cells padded in from shorter rows may be empty."""
    return any(is_sep_cell(cell) for cell in row) and all(
        is_sep_cell(cell) or cell == "" for cell in row
    )


def parse_alignment(cell: str) -> str:
    """Map a separator cell to ``""``/``left``/``center``/``right``."""
    left = cell.startswith(":")
    right = cell.endswith(":") and len(cell) > 1
    if left and right:
        return "center"
    if left:
        return "left"
    if right:
        return "right"
    return ""


def separator_widths(line: str) -> list[int]:
    """Column widths a separator row encodes in its physical cell widths.

    ``render_separator`` emits each cell at exactly the allocated width, so
    a formatted table carries its allocation here. A hand-written narrow
    separator (``|---|``) yields the dash run's own length."""
    stripped = line.strip()
    cells = re.split(r"(?<!\\)\|", stripped)
    if cells and stripped.startswith("|"):
        cells = cells[1:]
    if cells and stripped.endswith("|") and not stripped.endswith("\\|"):
        cells = cells[:-1]
    return [max(len(cell) - 2, len(cell.strip())) for cell in cells]


def continues(last: list[str], row: list[str], widths: list[int]) -> bool:
    """True when ``row`` is a continuation of the physical row ``last``.

    The greedy-wrap invariant of :func:`render_row`: a continuation chunk
    exists only because its first token would not fit at the end of the
    previous physical line. Every non-empty cell must satisfy it; the
    previous cell must itself be a plausible chunk (within the column
    width, or a single over-long atomic token). A chunk can also only live
    in a column that :func:`allocate` squeezed, and squeezed columns always
    end at the table's widest allocation (±1 remainder), never below
    ``MIN_COLUMN_WIDTH`` — a non-empty cell anywhere else marks a real
    row."""
    if not any(row):
        return False
    ceiling = max(widths, default=0)
    for i, cell in enumerate(row):
        if not cell:
            continue
        prev = last[i] if i < len(last) else ""
        width = widths[i] if i < len(widths) else 0
        if width < MIN_COLUMN_WIDTH or width < ceiling - 1:
            return False
        if not prev:
            return False
        if len(prev) > width and len(tokenize(prev)) > 1:
            return False
        if len(prev) + 1 + len(tokenize(cell)[0]) <= width:
            return False
    return True


def join_continuations(
    rows: list[list[str]], widths: list[int] | None = None
) -> list[list[str]]:
    """Re-merge physical continuation rows into logical rows.

    With ``widths`` (the separator row's column widths), a row is a
    continuation when :func:`continues` says so — the shape this formatter
    emits, whichever column wrapped. Without widths, only a row whose first
    cell is empty (and that has any content) is treated as a continuation.
    Joining first makes formatting idempotent and lets a table re-flow when
    the width budget changes.
    """
    logical: list[list[str]] = []
    last_physical: list[str] = []
    for row in rows:
        if logical and row and (
            continues(last_physical, row, widths)
            if widths is not None
            else (row[0] == "" and any(row))
        ):
            previous = logical[-1]
            for i, cell in enumerate(row):
                if cell:
                    previous[i] = f"{previous[i]} {cell}".strip()
        else:
            logical.append(list(row))
        last_physical = row
    return logical


def parse_table(lines: list[str], max_width: int = DEFAULT_MAX_WIDTH) -> Table:
    """Parse a run of ``|`` lines into a :class:`Table`."""
    parsed = [split_row(line) for line in lines]
    columns = max(len(row) for row in parsed)
    for row in parsed:
        row.extend([""] * (columns - len(row)))

    # The separator is the first all-dash row after the first physical row
    # (a wrapped header spans several physical rows before it); a body cell
    # that happens to contain `---` inside a wider row is content.
    sep_index = next(
        (i for i, row in enumerate(parsed) if i > 0 and is_separator_row(row)),
        None,
    )
    if sep_index is not None:
        widths = separator_widths(lines[sep_index])
        # Wrapping only happens once a table is squeezed to the budget; a
        # narrower table holds no continuation rows, and running the
        # invariant test against its narrow columns would merge real rows.
        if sum(widths) + 3 * len(widths) + 1 < max_width:
            join = lambda rows: [list(row) for row in rows]  # noqa: E731
        else:
            join = lambda rows: join_continuations(rows, widths)  # noqa: E731
        return Table(
            header_rows=join(parsed[:sep_index]),
            alignments=[parse_alignment(cell) for cell in parsed[sep_index]],
            body_rows=join(parsed[sep_index + 1 :]),
        )
    return Table(body_rows=join_continuations(parsed))


# ── Layout ───────────────────────────────────────────────────────────────


def tokenize(text: str) -> list[str]:
    """Split into wrap tokens without ever separating characters that were
    adjacent in the source — rejoining tokens with single spaces reproduces
    the (whitespace-normalized) text."""
    return TOKEN_RE.findall(text)


def wrap_cell(text: str, width: int) -> list[str]:
    """Greedy word-wrap of one cell to ``width``.

    Reimplements textwrap because the tokens are custom: a markdown link
    (plus anything glued to it) is atomic, and a single token longer than
    ``width`` overflows on its own line rather than being broken."""
    if len(text) <= width:
        return [text]
    lines: list[str] = []
    current = ""
    for token in tokenize(text):
        candidate = token if not current else f"{current} {token}"
        if len(candidate) <= width or not current:
            current = candidate
        else:
            lines.append(current)
            current = token
    if current:
        lines.append(current)
    return lines


def allocate(widths: list[int], budget: int) -> list[int]:
    """Water-fill: repeatedly cap only the widest columns — shaving them down
    toward the next-widest, never below ``MIN_COLUMN_WIDTH`` — until the
    total fits ``budget`` or nothing more can be reduced."""
    alloc = list(widths)
    while sum(alloc) > budget:
        widest = max(alloc)
        next_widest = max([w for w in alloc if w < widest], default=MIN_COLUMN_WIDTH)
        floor = max(next_widest, MIN_COLUMN_WIDTH)
        at_max = [i for i, w in enumerate(alloc) if w == widest]
        reducible = (widest - floor) * len(at_max)
        if reducible <= 0:
            break
        cut = min(sum(alloc) - budget, reducible)
        per_column, remainder = divmod(cut, len(at_max))
        for position, i in enumerate(at_max):
            alloc[i] = widest - per_column - (1 if position < remainder else 0)
    return alloc


# ── Rendering ────────────────────────────────────────────────────────────


def render_separator(alignments: list[str], alloc: list[int]) -> str:
    cells = []
    for alignment, width in zip(alignments, alloc):
        if alignment == "center":
            cells.append(":" + "-" * (width - 2) + ":")
        elif alignment == "left":
            cells.append(":" + "-" * (width - 1))
        elif alignment == "right":
            cells.append("-" * (width - 1) + ":")
        else:
            cells.append("-" * width)
    return "| " + " | ".join(cells) + " |"


def render_row(row: list[str], alloc: list[int]) -> list[str]:
    """Render one logical row as one or more physical rows: the tallest
    wrapped cell sets the height, shorter cells pad with blanks."""
    columns = len(alloc)
    wrapped = [wrap_cell(row[i], alloc[i]) for i in range(columns)]
    height = max(len(chunks) for chunks in wrapped)
    out = []
    for line_index in range(height):
        cells = []
        for i in range(columns):
            chunk = wrapped[i][line_index] if line_index < len(wrapped[i]) else ""
            cells.append(chunk.ljust(alloc[i]))
        out.append("| " + " | ".join(cells) + " |")
    return out


def format_table(table: Table, max_width: int = DEFAULT_MAX_WIDTH) -> list[str]:
    """Lay out a :class:`Table` as padded, wrapped physical lines."""
    columns = table.columns
    widths = [0] * columns
    for row in table.logical_rows:
        for i, cell in enumerate(row):
            widths[i] = max(widths[i], len(cell))
    widths = [max(width, 3) for width in widths]
    overhead = 3 * columns + 1  # "| " separators and the closing "|"
    alloc = allocate(widths, max_width - overhead)

    out = []
    for row in table.header_rows:
        out.extend(render_row(row, alloc))
    if table.alignments is not None:
        out.append(render_separator(table.alignments, alloc))
    for row in table.body_rows:
        out.extend(render_row(row, alloc))
    return out


# ── Document processing ──────────────────────────────────────────────────


def closes_fence(opening: str, line: str) -> bool:
    """A closing fence uses the same character, is at least as long as the
    opening fence, and carries nothing else on the line (CommonMark)."""
    char, length = opening[0], len(opening)
    return re.fullmatch(rf"\s*{re.escape(char)}{{{length},}}\s*", line) is not None


def format_markdown(text: str, max_width: int = DEFAULT_MAX_WIDTH) -> str:
    """Format every markdown table in ``text``; everything else — including
    fenced code blocks — passes through untouched. Pure function."""
    out: list[str] = []
    open_fence: str | None = None
    table: list[str] = []

    def flush() -> None:
        if table:
            out.extend(format_table(parse_table(table, max_width), max_width))
            table.clear()

    for line in text.split("\n"):
        if open_fence is not None:
            out.append(line)
            if closes_fence(open_fence, line):
                open_fence = None
            continue
        fence = FENCE_RE.match(line)
        if fence:
            flush()
            open_fence = fence.group(1)
            out.append(line)
            continue
        if line.lstrip().startswith("|") and line.count("|") >= 2:
            table.append(line)
            continue
        flush()
        out.append(line)
    flush()
    return "\n".join(out)


def format_file(
    path: str | Path, max_width: int = DEFAULT_MAX_WIDTH, check: bool = False
) -> bool:
    """Rewrite ``path`` in place (or, with ``check``, only report); returns
    True when the file changed / would change."""
    path = Path(path)
    original = path.read_text(encoding="utf-8")
    formatted = format_markdown(original, max_width)
    if formatted == original:
        return False
    if not check:
        path.write_text(formatted, encoding="utf-8")
    return True


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Format markdown tables for plain-text readability: "
        "pad columns, wrap long cells into continuation rows."
    )
    parser.add_argument("files", nargs="+", metavar="FILE")
    parser.add_argument(
        "--max-width",
        type=int,
        default=DEFAULT_MAX_WIDTH,
        help=f"table width budget in characters (default {DEFAULT_MAX_WIDTH})",
    )
    parser.add_argument(
        "--check",
        action="store_true",
        help="write nothing; exit 1 if any file would change",
    )
    args = parser.parse_args(argv)

    changed = []
    for path in args.files:
        if format_file(path, max_width=args.max_width, check=args.check):
            changed.append(path)
            print(f"{'would format' if args.check else 'formatted'} {path}")
    return 1 if (args.check and changed) else 0


if __name__ == "__main__":
    sys.exit(main())
