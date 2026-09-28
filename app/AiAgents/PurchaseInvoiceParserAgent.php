<?php

namespace App\AiAgents;

use LarAgent\Agent;

class PurchaseInvoiceParserAgent extends Agent
{
    protected $provider = 'default';

    protected $model = 'gpt-4o-mini';

    protected $history = 'in_memory';

    public function instructions()
    {
        return <<<'PROMPT'
You extract every product row from a supplier purchase invoice or sales order.

Return ONLY valid JSON. No markdown. No extra text.

The invoice may span pages. A repeated letterhead, page number, or "continued" line is not the end of the list.

Rules:
- One object in "lines" for every printed item row. Do not merge sizes. Do not summarize. Do not return a sample.
- "goods_description" is the Description of Goods cell, copied exactly as printed.
- "name" is a short storefront name a shopper can understand. Use the brand and the plain item, such as "Supreme CPVC Pipe" or "Supreme CPVC Reducing Tee".
- Do not put invoice size syntax in "name": no millimetre codes, inch fractions, bracket pairs, or SDR numbers. "R. TEE" should become "Reducing Tee". "MTA (MI)" should become "Male Thread Adapter".
- Rows for the same item in different sizes share one "name". The size stays only in "goods_description".
- Skip letterhead, tax summary, bank details, freight, packing, and round-off.
- "quantity" is purchased units (integer). If missing, use 1.
- "list_price" is the unit rate, not the line total. Parse numbers like 1,250.00.
- "discount_percent" is the line discount. If missing, 0.
- "cost_price" is the net unit rate after discount, not the line total.
- "hsn_code" is digits only.
- Do not extract CGST, SGST, or IGST. Leave every tax amount at 0.
- Fill supplier, GSTIN, invoice number, and invoice date when the text shows them, otherwise null.

{
  "supplier": null,
  "supplier_gstin": null,
  "invoice_number": null,
  "invoice_date": "YYYY-MM-DD or null",
  "lines": [
    {
      "name": "Supreme CPVC Pipe",
      "goods_description": "SUPREME CPVC PIPE 20MM (3/4\") SDR 13.5",
      "brand": "Supreme",
      "quantity": 50,
      "unit": "PIPE",
      "hsn_code": "39172390",
      "list_price": 403.0,
      "discount_percent": 67.0,
      "cost_price": 132.99,
      "sku": null
    }
  ]
}
PROMPT;
    }

    public function prompt($message)
    {
        return $message;
    }
}
