<?php

declare(strict_types=1);

namespace ScadenzeFatture;

final class ContactsRepository
{
    /** @return array<string, array{name:string, phone:?string, email:?string}> */
    public function loadFromCsv(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'rb');
        if (!$handle) {
            return [];
        }

        $header = fgetcsv($handle, separator: ';');
        if (!$header) {
            fclose($handle);
            return [];
        }

        $normalizedHeader = array_map(static fn (string $value): string => strtolower(trim($value)), $header);
        $contacts = [];

        while (($row = fgetcsv($handle, separator: ';')) !== false) {
            $assoc = [];
            foreach ($normalizedHeader as $index => $column) {
                $assoc[$column] = isset($row[$index]) ? trim((string) $row[$index]) : '';
            }

            $keyCandidates = [
                self::normalizeKey($assoc['piva'] ?? ''),
                self::normalizeKey($assoc['codice_fiscale'] ?? ''),
                self::normalizeKey($assoc['cliente'] ?? ''),
            ];

            foreach ($keyCandidates as $key) {
                if ($key === '') {
                    continue;
                }

                $contacts[$key] = [
                    'name' => $assoc['cliente'] ?? '',
                    'phone' => $assoc['telefono'] ?? null,
                    'email' => $assoc['email'] ?? null,
                ];
            }
        }

        fclose($handle);

        return $contacts;
    }

    public static function normalizeKey(?string $value): string
    {
        return strtoupper(trim((string) $value));
    }
}
