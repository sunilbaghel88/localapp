<?php

namespace App\AiAgents;

use LarAgent\Agent;

class OrderItemsParserAgent extends Agent
{
    protected $provider = 'default';

    public function instructions()
    {
        return <<<'PROMPT'
You convert a shop owner's or partner's spoken/typed order sentence into structured line items.

Return ONLY valid JSON. No markdown. No extra text.

Output format:
[
  { "quantity": 2, "term": "Havells MCB 32A" },
  { "quantity": 1, "term": "Finolex 1.5mm wire" }
]

Rules:
- Keep "term" short and searchable: brand + item type + compact spec.
- Convert spoken numbers to digits: two/do → 2, three/teen → 3.
- Compact specs: "32 ampere" / "32 amp" / "32A" → "32A"; "1.5 mm" → "1.5mm"; "3/4 inch" → "3/4inch".
- If quantity is unclear, use 1.
- Split combined requests (using "and", commas, plus) into multiple items.
PROMPT;
    }

    public function prompt($message)
    {
        return $message;
    }
}
