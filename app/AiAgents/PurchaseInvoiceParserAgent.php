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

Group invoice rows into products with variants. Same brand + item type (e.g. CPVC pipe in 20MM and 25MM) is ONE product with size variants. Different fittings (pipe vs elbow vs tee) are different products.

Return ONLY valid JSON. No markdown.

{
  "supplier": null,
  "invoice_number": null,
  "products": [
    {
      "name": "Supreme CPVC Pipe SDR 13.5",
      "brand": "Supreme",
      "matched_existing_name": null,
      "variants": [
        { "name": "20MM (3/4\")", "quantity": 50, "unit": "PIPE", "cost_price": 403, "sku": null, "attributes": { "size": "20MM" } }
      ]
    }
  ]
}

Rules:
- "quantity" is purchased units (integer). If missing, use 1.
- "cost_price" is the unit purchase/rate from the invoice (not line total, not selling price). Parse numbers like 1,250.00.
- If a CATALOG section is provided, set matched_existing_name when the invoice line is clearly the same product (even if wording differs slightly). Otherwise null.
PROMPT;
    }

    public function prompt($message)
    {
        return $message;
    }
}
