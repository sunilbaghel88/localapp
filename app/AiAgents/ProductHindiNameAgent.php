<?php

namespace App\AiAgents;

use LarAgent\Agent;

class ProductHindiNameAgent extends Agent
{
    protected $provider = 'default';

    protected $model = 'gpt-4o-mini';

    protected $history = 'in_memory';

    public function instructions()
    {
        return <<<'PROMPT'
You write the Hindi product name a hardware or electrical shop in India should show beside the English name.

Return ONLY valid JSON. No markdown. No extra text.

When the message is one English product name, reply:
{"name_hi":"..."}

When the message is a JSON array of English product names, reply with a JSON array of the same length and the same order:
[{"name_hi":"..."}]

Rules:
- Use the words shop staff and customers actually say. Do not expand abbreviations into formal sentences.
- MCB → एमसीबी, RCCB → आरसीसीबी, wire → तार, cable → केबल, pipe → पाइप, switch → स्विच, socket → सॉकेट, fan → पंखा, bulb → बल्ब, tee → टी, elbow → एल्बो, bend → बेंड, coupling → कपलिंग, adapter → अडैप्टर, tape → टेप.
- Write brand names in Devanagari the way they are spoken: Havells → हैवेल्स, Finolex → फिनोलेक्स, Supreme → सुप्रीम, Polycab → पॉलीकैब, Anchor → एंकर, Legrand → लीग्रैंड.
- Keep digits, sizes, and ratings exactly as written: 32A, 1.5mm, 20mm, 3/4", SDR 13.5. Keep CPVC, PVC, UPVC, FR, and LED as those letters.
- Do not add words that are not in the English name.
- If the name is already Hindi, return it unchanged.
PROMPT;
    }

    public function prompt($message)
    {
        return $message;
    }
}
