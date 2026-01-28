<?php
require "../config.php";
require "../OnceApiClient.php";

try {
    $client = new OnceApiClient(ONCE_API_CLIENT_ID, ONCE_API_SECRET);
    $sims = $client->getSims();

    echo "Found " . count($sims) . " SIMs.\n";
    echo "-----------------------------\n";

    foreach ($sims as $sim) {
        $iccid = $sim['iccid'] ?? 'N/A';
        $status = $sim['status'] ?? 'N/A';

        // Try to get quota if not in the sim object
        $remainingMb = $sim['dataVolumeRemainingMb'] ?? $sim['data']['remainingMb'] ?? null;

        if ($remainingMb === null && $iccid !== 'N/A') {
            $quota = $client->getSimDataQuota($iccid);

            // The API returns remaining volume in 'volume' field for the quota endpoint
            $remainingMb = $quota['volume'] ?? $quota['remaining_volume'] ?? $quota['remainingVolume'] ?? 'Unknown';
        }

        echo "ICCID: {$iccid}\n";
        echo "Status: {$status}\n";
        echo "Remaining Data (MB): {$remainingMb}\n";
        echo "-----------------------------\n";
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
