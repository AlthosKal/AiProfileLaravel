<?php

namespace Modules\Transaction\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Uri('skill://documents/pdf')]
#[Description('Instructions for generating PDF documents using the ExecuteDocumentScript tool.')]
class PdfDocumentSkillResource extends Resource
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
# PDF Document Generation Skill

## Overview
Use the `execute_document_script` tool to generate PDF files dynamically.
Write a Python script that uses `reportlab` (for structured documents) or
`matplotlib` (for chart-heavy documents). The script must save its output
to `os.path.join(os.environ["OUTPUT_DIR"], "<your_filename>.pdf")`.

## Available Libraries
- `reportlab` — page layout, text, tables, vector graphics
- `matplotlib` — charts and plots (save as PDF with `plt.savefig`)
- `pandas` — data manipulation before rendering
- `Pillow` — image embedding

## Document Structure (reportlab)

### Fixed Header (always include this block)
```python
import os
from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import cm
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, Image
from reportlab.platypus import HRFlowable

output_path = os.path.join(os.environ["OUTPUT_DIR"], "document.pdf")
doc = SimpleDocTemplate(output_path, pagesize=A4,
                        leftMargin=2*cm, rightMargin=2*cm,
                        topMargin=2.5*cm, bottomMargin=2*cm)
styles = getSampleStyleSheet()
story = []

# --- FIXED HEADER ---
header_style = ParagraphStyle('Header', parent=styles['Normal'],
                               fontSize=10, textColor=colors.HexColor('#64748b'))
title_style = ParagraphStyle('Title', parent=styles['Title'],
                              fontSize=20, textColor=colors.HexColor('#1e293b'), spaceAfter=0)

story.append(Paragraph("AI Financial Assistant", header_style))
story.append(Paragraph("<b>Report Title Here</b>", title_style))
story.append(Spacer(1, 0.3*cm))
story.append(HRFlowable(width="100%", thickness=1, color=colors.HexColor('#e2e8f0')))
story.append(Spacer(1, 0.5*cm))
# --- END FIXED HEADER ---
```

### Adding a Table
```python
from reportlab.platypus import Table, TableStyle
from reportlab.lib import colors

data = [
    ["Column A", "Column B", "Column C"],   # header row
    ["Value 1",  "Value 2",  "Value 3"],
    # ... more rows
]

table = Table(data, colWidths=[5*cm, 5*cm, 5*cm])
table.setStyle(TableStyle([
    ('BACKGROUND',  (0,0), (-1,0),  colors.HexColor('#1e293b')),
    ('TEXTCOLOR',   (0,0), (-1,0),  colors.white),
    ('FONTNAME',    (0,0), (-1,0),  'Helvetica-Bold'),
    ('FONTSIZE',    (0,0), (-1,-1), 9),
    ('ROWBACKGROUNDS', (0,1), (-1,-1), [colors.white, colors.HexColor('#f8fafc')]),
    ('GRID',        (0,0), (-1,-1), 0.5, colors.HexColor('#e2e8f0')),
    ('ALIGN',       (0,0), (-1,-1), 'LEFT'),
    ('VALIGN',      (0,0), (-1,-1), 'MIDDLE'),
    ('TOPPADDING',  (0,0), (-1,-1), 5),
    ('BOTTOMPADDING',(0,0), (-1,-1), 5),
]))
story.append(table)
story.append(Spacer(1, 0.5*cm))
```

### Adding a Chart (matplotlib embedded in PDF)
```python
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import io
from reportlab.platypus import Image as RLImage

fig, ax = plt.subplots(figsize=(6, 3))
ax.bar(["Jan", "Feb", "Mar"], [1200, 980, 1450], color='#3b82f6')
ax.set_title("Monthly Revenue")
ax.set_ylabel("Amount")
plt.tight_layout()

buf = io.BytesIO()
fig.savefig(buf, format='png', dpi=150, bbox_inches='tight')
buf.seek(0)
plt.close(fig)

story.append(RLImage(buf, width=14*cm, height=7*cm))
story.append(Spacer(1, 0.5*cm))
```

### Build the Document (always end with this)
```python
doc.build(story)
```

## Tool Call Template
```json
{
  "code": "<full python script>",
  "output_filename": "report.pdf"
}
```

## Rules
1. Always read data from the tool parameters or previously retrieved tool results — never hardcode business data.
2. Always include the fixed header block.
3. The output filename must end in `.pdf` and match the `output_filename` parameter.
4. Use `matplotlib.use('Agg')` before importing pyplot to avoid display errors in headless mode.
5. All monetary values must be formatted with two decimal places.
SKILL;
    }
}
