<?php
// Archives every agent's Tata Sky DTH search result to a shared file on the D:
// drive - same architecture/reasoning as includes/hpgas_archive.php (file-
// based, not a DB table, so this survives independently of the CRM's own
// database if that's ever wiped/migrated). A single Tata Sky DTH search returns
// ONE subscriber's record grouped into named sections (Subscriber Details,
// Address, one "Digicard (<number>)" section per set-top box - see
// Gas/lpg_web/tata_dth.py's run_tata_dth_single()), so this writes exactly
// one archive row per found search, same as HP Gas.

const TATADTH_ARCHIVE_DIR = 'D:/TataDthSearchArchive';
const TATADTH_ARCHIVE_CSV = TATADTH_ARCHIVE_DIR . '/records.csv';
const TATADTH_ARCHIVE_INDEX = TATADTH_ARCHIVE_DIR . '/dedup_index.txt';

// Best-effort archive: never let a D:-drive/permission problem break the
// actual search response an agent is waiting on. $sections is the
// "sections" array from a found tata_dth_api.php response - each section is
// ['title' => ..., 'fields' => [['label' => ..., 'value' => ...], ...]].
function archiveTataDthResults(array $sections, string $searchedBy, string $query): void {
    try {
        if (!is_dir(TATADTH_ARCHIVE_DIR) && !@mkdir(TATADTH_ARCHIVE_DIR, 0777, true)) return;

        // Every label tata_dth.py's ACCOUNT_FIELDS/ADDRESS_FIELDS/
        // DIGICARD_COLUMNS can produce - "Digicard Status" (not just
        // "Status") deliberately avoids colliding with the account's own
        // "Status" field, same reasoning noted in tata_dth.py itself.
        $dataColumns = [
            'Subscriber Name', 'Subscriber Id', 'Status', 'Account Type', 'Account Category',
            'Account Sub-Category', 'Tier', 'Sales Segment', 'Balance Lock Status',
            'Building Name', 'Address Line 1', 'Address Line 2', 'Village/Town/City', 'Town',
            'District', 'Tahsil', 'State', 'Pin Code',
            'Product', 'Digicard #', 'Digicomp #', 'Digicard Type', 'Digicard Status',
            'Effective Start Date', 'DigiComp Mfg. Serial Number', 'Asset Type',
        ];
        $header = array_merge(['Timestamp', 'Searched By', 'Query'], $dataColumns, ['Other Fields']);

        $seenHashes = is_file(TATADTH_ARCHIVE_INDEX)
            ? array_flip(file(TATADTH_ARCHIVE_INDEX, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            : [];

        // Flatten every section's fields into one label => value map first -
        // dedup and column-mapping both work on the whole record at once,
        // same as includes/hpgas_archive.php.
        $flatFields = [];
        foreach ($sections as $section) {
            foreach (($section['fields'] ?? []) as $field) {
                $label = trim((string) ($field['label'] ?? ''));
                if ($label === '') continue;
                $flatFields[$label] = (string) ($field['value'] ?? '');
            }
        }
        if (!$flatFields) return;

        // Dedup on the record's OWN data, not the search query - re-running
        // the same search (or a different number landing on the same
        // subscriber) should still only be stored once.
        $fieldsForHash = $flatFields;
        ksort($fieldsForHash);
        $hash = md5(json_encode($fieldsForHash));
        if (isset($seenHashes[$hash])) return;

        $row = array_fill_keys($dataColumns, '');
        $other = [];
        foreach ($flatFields as $label => $value) {
            if (in_array($label, $dataColumns, true)) {
                $row[$label] = $value;
            } else {
                $other[] = "$label: $value";
            }
        }
        $row['Timestamp'] = date('Y-m-d H:i:s');
        $row['Searched By'] = $searchedBy;
        $row['Query'] = $query;
        $row['Other Fields'] = implode('; ', $other);

        $isNewFile = !is_file(TATADTH_ARCHIVE_CSV);
        $fh = @fopen(TATADTH_ARCHIVE_CSV, 'a');
        if ($fh && flock($fh, LOCK_EX)) {
            if ($isNewFile) fputcsv($fh, $header);
            fputcsv($fh, array_map(fn($c) => $row[$c] ?? '', $header));
            flock($fh, LOCK_UN);
        }
        if ($fh) fclose($fh);

        $ih = @fopen(TATADTH_ARCHIVE_INDEX, 'a');
        if ($ih && flock($ih, LOCK_EX)) {
            fwrite($ih, $hash . "\n");
            flock($ih, LOCK_UN);
        }
        if ($ih) fclose($ih);
    } catch (Throwable $e) {
        // Archiving is best-effort - a D:-drive or permission issue must
        // never surface as a search failure to the agent.
    }
}
