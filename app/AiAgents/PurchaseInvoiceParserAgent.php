<?php

namespace App\AiAgents;

use LarAgent\Agent;

class PurchaseInvoiceParserAgent extends Agent
{
    protected $provider = 'default';

    protected $model = 'gpt-4o-mini';

    protected $history = \LarAgent\History\InMemoryChatHistory::class;

    public function instructions()
    {
        return <<<'PROMPT'
You extract purchased goods from a supplier purchase invoice.

The invoice layout varies by supplier. Ignore letterheads, GST/tax tables, bank details, and totals. Focus on line items (products/goods).

Return ONLY valid JSON. No markdown. No extra text.

Output format:
{
  "supplier": "optional supplier name or null",
  "invoice_number": "optional invoice no or null",
  "items": [
    {
      "name": "clean product name for a shop catalog",
      "brand": "brand if clearly present else null",
      "quantity": 10,
      "unit": "pcs",
      "cost_price": 125.5,
      "sku": "supplier sku/hsn/code if present else null",
      "matched_existing_name": "exact catalog name if this is the same product else null"
    }
  ]
}

Rules:
- "name" must be a sellable product title (brand + item + key specs). Drop invoice-only noise (HSN columns, tax %, amounts as names).
- "quantity" is purchased units (integer). If missing, use 1.
- "cost_price" is the unit purchase/rate from the invoice (not line total, not selling price). Parse numbers like 1,250.00.
- Merge obvious duplicate lines of the same product by summing quantity.
- If a CATALOG section is provided, set matched_existing_name when the invoice line is clearly the same product (even if wording differs slightly). Otherwise null.
- Skip freight, packing, round-off, and tax-only rows.
PROMPT;
    }

    public function prompt($message)
    {
        return $message;
    }
}
