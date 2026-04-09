<?php

namespace Modules\Transaction\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Uri('skill://documents/excel')]
#[Description('Instructions for generating Excel (.xlsx) documents using the ExecuteDocumentScript tool.')]
class ExcelDocumentSkillResource extends Resource
{
    /**
     * Handle the resource request.
     */
    public function handle(Request $request): Response
    {
        return Response::text($this->skill());
    }

    private function skill(): string
    {
        return <<<'SKILL'
# Excel Document Generation Skill

## Overview
Use the `execute_document_script` tool to generate Excel (.xlsx) files dynamically.
Write a Python script that uses `openpyxl`. The script must save its output
to `os.path.join(os.environ["OUTPUT_DIR"], "<your_filename>.xlsx")`.

## Available Libraries
- `openpyxl` — workbook, sheets, styling, charts, formulas
- `pandas` — data manipulation (use `to_excel` with openpyxl engine)
- `matplotlib` — generate chart images to embed in sheets

## Workbook Structure

### Fixed Header (always include this block)
```python
import os
from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side, numbers
from openpyxl.utils import get_column_letter

output_path = os.path.join(os.environ["OUTPUT_DIR"], "document.xlsx")
wb = Workbook()
ws = wb.active
ws.title = "Report"

# --- FIXED HEADER STYLES ---
HEADER_FILL   = PatternFill("solid", fgColor="1E293B")   # dark slate
HEADER_FONT   = Font(name="Calibri", bold=True, color="FFFFFF", size=11)
SUBHEAD_FILL  = PatternFill("solid", fgColor="3B82F6")   # blue
SUBHEAD_FONT  = Font(name="Calibri", bold=True, color="FFFFFF", size=10)
DATA_FONT     = Font(name="Calibri", size=10)
ALT_FILL      = PatternFill("solid", fgColor="F8FAFC")   # light gray
BORDER_SIDE   = Side(style="thin", color="E2E8F0")
CELL_BORDER   = Border(left=BORDER_SIDE, right=BORDER_SIDE,
                        top=BORDER_SIDE, bottom=BORDER_SIDE)

# --- REPORT TITLE (row 1) ---
ws.merge_cells("A1:F1")
title_cell = ws["A1"]
title_cell.value = "Report Title Here"
title_cell.font = Font(name="Calibri", bold=True, size=14, color="1E293B")
title_cell.alignment = Alignment(horizontal="left", vertical="center")
ws.row_dimensions[1].height = 30

ws.merge_cells("A2:F2")
subtitle_cell = ws["A2"]
subtitle_cell.value = "AI Financial Assistant — Generated Report"
subtitle_cell.font = Font(name="Calibri", size=9, color="64748B", italic=True)
ws.row_dimensions[2].height = 16

ws.append([])  # blank row 3
# --- END FIXED HEADER ---
```

### Writing a Data Table with Column Headers
```python
# Column headers (row 4)
columns = ["Date", "Name", "Amount", "Type", "Description"]
ws.append(columns)
header_row = ws.max_row

for col_idx, _ in enumerate(columns, start=1):
    cell = ws.cell(row=header_row, column=col_idx)
    cell.fill = HEADER_FILL
    cell.font = HEADER_FONT
    cell.alignment = Alignment(horizontal="center", vertical="center")
    cell.border = CELL_BORDER
ws.row_dimensions[header_row].height = 20

# Data rows
rows = [
    ["2024-01-01", "Salary", 3000.00, "income", "Monthly salary"],
    # ... populate from tool result data
]
for row_idx, row_data in enumerate(rows):
    ws.append(row_data)
    data_row = ws.max_row
    fill = ALT_FILL if row_idx % 2 == 0 else PatternFill()
    for col_idx in range(1, len(row_data) + 1):
        cell = ws.cell(row=data_row, column=col_idx)
        cell.font = DATA_FONT
        cell.fill = fill
        cell.border = CELL_BORDER
        cell.alignment = Alignment(vertical="center")

# Auto-fit column widths
for col in ws.columns:
    max_len = max((len(str(cell.value)) for cell in col if cell.value), default=10)
    ws.column_dimensions[get_column_letter(col[0].column)].width = min(max_len + 4, 40)
```

### Adding a Chart (openpyxl native)
```python
from openpyxl.chart import BarChart, Reference

chart = BarChart()
chart.title = "Monthly Summary"
chart.y_axis.title = "Amount"
chart.x_axis.title = "Month"
chart.style = 10

# Reference the data range already written in the sheet
data_ref = Reference(ws, min_col=3, min_row=header_row, max_row=ws.max_row)
cats_ref  = Reference(ws, min_col=1, min_row=header_row + 1, max_row=ws.max_row)
chart.add_data(data_ref, titles_from_data=True)
chart.set_categories(cats_ref)
chart.shape = 4
ws.add_chart(chart, "H4")
```

### Adding a Summary Sheet
```python
ws_summary = wb.create_sheet(title="Summary")
# ... populate summary metrics
```

### Save the Workbook (always end with this)
```python
wb.save(output_path)
```

## Tool Call Template
```json
{
  "code": "<full python script>",
  "output_filename": "report.xlsx"
}
```

## Rules
1. Always read data from the tool parameters or previously retrieved tool results — never hardcode business data.
2. Always include the fixed header block (rows 1-3).
3. The output filename must end in `.xlsx` and match the `output_filename` parameter.
4. Format monetary columns with two decimal places using `numbers.FORMAT_NUMBER_COMMA_SEPARATED1`.
5. Freeze the header row with `ws.freeze_panes = "A5"` when there are data tables.
SKILL;
    }
}
