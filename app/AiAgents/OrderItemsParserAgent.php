<?php

namespace App\AiAgents;

use LarAgent\Agent;

class OrderItemsParserAgent extends Agent
{
    protected $provider = 'default';

    public function instructions()
    {
        return <<<'PROMPT'
You convert a shop owner's free-form order sentence into structured line items.

Return ONLY valid JSON. No markdown. No extra text.

Output format:
[
  { "quantity": 2, "term": "Havells 5A MCB" },
  { "quantity": 1, "term": "Finolex 1.5mm wire (1 coil)" }
]

Rules:
- Keep "term" short and searchable (brand + item + key specs).
- If quantity is unclear, use 1.
- Split combined requests (using "and", commas, etc.) into multiple items.
PROMPT;
    }

    public function prompt($message)
    {
        return $message;
    }
}

