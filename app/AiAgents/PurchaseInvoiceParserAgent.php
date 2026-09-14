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

The invoice layout varies by supplier.

Group invoice rows into products with variants. Same brand + item type (e.g. CPVC pipe in 20MM and 25MM) is ONE product with size variants. Different fittings (pipe vs elbow vs tee) are different products.

Return ONLY valid JSON. No markdown.

{
  "supplier": "supplier name",
  "supplier_gstin": "15-char GSTIN or null",
  "invoice_number": "invoice/order no or null",
  "invoice_date": "YYYY-MM-DD or null",
  "cgst_amount": 0,
  "sgst_amount": 0,
  "igst_amount": 0,
  "products": [
    {
      "name": "Supreme CPVC Pipe SDR 13.5",
      "brand": "Supreme",
      "hsn_code": "39172390",
      "matched_existing_name": null,
      "variants": [
        {
          "name": "20MM (3/4\")",
          "quantity": 50,
          "unit": "PIPE",
          "hsn_code": "39172390",
          "list_price": 403,
          "discount_percent": 67,
          "cost_price": 132.99,
          "cgst_amount": 598.45,
          "sgst_amount": 598.45,
          "igst_amount": 0,
          "sku": null,
          "attributes": { "size": "20MM" }
        }
      ]
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
