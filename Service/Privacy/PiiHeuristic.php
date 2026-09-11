<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Privacy;

/**
 * Detects personal data in free text and replaces each hit with a vault token (issue #97). Used for
 * the paths where there is no declared field to classify: the admin's typed message, model-generated
 * tool-call arguments, and non-denied page_form field values.
 *
 * Only near-zero-false-positive classes are matched: email, IBAN (mod-97), Dutch BSN (11-proef),
 * Dutch VAT, and a hard-anchored Dutch phone number. Checksums keep an ordinary order id or SKU from
 * being mistaken for a BSN or IBAN. Anything without a signature (a bare name in prose) is out of
 * reach here by design and stays a documented residual risk.
 */
class PiiHeuristic
{
    private const EMAIL = '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i';
    private const IBAN = '/\b[A-Z]{2}\d{2}[A-Z0-9]{10,30}\b/i';
    private const VAT_NL = '/\bNL\d{9}B\d{2}\b/i';
    private const BSN = '/\b\d{3}[\s.\-]?\d{3}[\s.\-]?\d{3}\b/';
    private const PHONE_NL = '/(?:\+31|0031|0)[\s\-]?6(?:[\s\-]?\d){8}\b/';

    public function tokeniseFreeText(string $text, ConversationVault $vault): string
    {
        $text = $this->replace(self::EMAIL, $text, static fn (): bool => true, 'email', $vault);
        $text = $this->replace(self::IBAN, $text, fn (string $m): bool => $this->isValidIban($m), 'iban', $vault);
        $text = $this->replace(self::VAT_NL, $text, static fn (): bool => true, 'vat', $vault);
        $text = $this->replace(self::BSN, $text, fn (string $m): bool => $this->isValidBsn($m), 'bsn', $vault);
        $text = $this->replace(self::PHONE_NL, $text, static fn (): bool => true, 'phone', $vault);

        return $text;
    }

    /**
     * @param callable(string):bool $accept
     */
    private function replace(string $pattern, string $text, callable $accept, string $type, ConversationVault $vault): string
    {
        return (string)preg_replace_callback(
            $pattern,
            fn (array $m): string => $accept($m[0]) ? $vault->tokenise($m[0], $type) : $m[0],
            $text
        );
    }

    private function isValidIban(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $digits = '';
        foreach (str_split($rearranged) as $char) {
            $digits .= ctype_alpha($char) ? (string)(ord($char) - 55) : $char;
        }

        return $this->mod97($digits) === 1;
    }

    private function mod97(string $digits): int
    {
        $remainder = 0;
        foreach (str_split($digits) as $digit) {
            $remainder = ($remainder * 10 + (int)$digit) % 97;
        }

        return $remainder;
    }

    private function isValidBsn(string $bsn): bool
    {
        $bsn = preg_replace('/\D/', '', $bsn) ?? '';
        if (strlen($bsn) !== 9 || (int)$bsn === 0) {
            return false;
        }

        $sum = 0;
        foreach (str_split($bsn) as $index => $digit) {
            $weight = $index === 8 ? -1 : 9 - $index;
            $sum += $weight * (int)$digit;
        }

        return $sum % 11 === 0;
    }
}
