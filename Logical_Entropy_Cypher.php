<?php
// Logical Entropy Cypher v1.0
// Encryption Engine Based on Logical Entropy
// A practical and educational approach to wrapping standard ciphers (such as AES‑256‑GCM) 
// with a layer of 'logical entropy' that adds structural variability, configurable diffusion, 
// and authentication of the logic itself. It is designed to integrate 
// without breaking compatibility or infrastructure.
// Author: Roberto Aleman, ventics.com
// License: GNU GPL v3
// 

/// --- Minimal cryptographic utilities ---

function hkdf_sha256($ikm, $salt, $info, $len) {
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $t = '';
    $okm = '';
    for ($b = 1; strlen($okm) < $len; $b++) {
        $t = hash_hmac('sha256', $t . $info . chr($b), $prk, true);
        $okm .= $t;
    }
    return substr($okm, 0, $len);
}

function hmac_ctr_stream($key, $nonce, $counter, $len) {
    $out = '';
    for ($i = 0; strlen($out) < $len; $i++) {
        $blk = pack('N', $counter + $i) . $nonce;
        $out .= hash_hmac('sha256', $blk, $key, true);
    }
    return substr($out, 0, $len);
}

function aes256gcm_encrypt($key, $nonce_gcm, $ad, $plaintext) {
    $tag = '';
    $cipher = openssl_encrypt(
        $plaintext, 'aes-256-gcm', $key,
        OPENSSL_RAW_DATA, $nonce_gcm, $tag, $ad
    );
    return [$cipher, $tag];
}

function aes256gcm_decrypt($key, $nonce_gcm, $ad, $cipher, $tag) {
    return openssl_decrypt(
        $cipher, 'aes-256-gcm', $key,
        OPENSSL_RAW_DATA, $nonce_gcm, $tag, $ad
    );
}

// --- Logical entropy layer (lite version) ---

function logic_entropy_lite_encrypt($seed512, $nonce_logic, $msg) {
    $version = "le-lite-v1";
    if (strlen($seed512) !== 64) throw new Exception("seed must be 512 bits (64 bytes)");
    if (strlen($nonce_logic) !== 12) throw new Exception("nonce_logic must be 96 bits (12 bytes)");

    // Domain key derivation
    $material = hkdf_sha256($seed512, "salt|$version", "info|$version", 32*4);
    $Kseg  = substr($material, 0, 32);
    $Kperm = substr($material, 32, 32);
    $Kmix  = substr($material, 64, 32);
    $Kaead = substr($material, 96, 32);

    // 1) Segmentation: choose k and simple cuts
    $L = strlen($msg);
    $k = max(2, min(8, ord(hash_hmac('sha256', "k|".$nonce_logic, $Kseg, true)[0]) % 8 + 2));
    // Approximately uniform cuts with slight deterministic jitter
    $cuts = [];
    $base = intdiv($L, $k);
    $rem = $L % $k;
    $offset = 0;
    for ($i = 0; $i < $k-1; $i++) {
        $jitter = ord(hash_hmac('sha256', "c|$i|".$nonce_logic, $Kseg, true)[0]) % 2; // 0..1
        $len = $base + ($i < $rem ? 1 : 0);
        $len = max(1, $len + ($jitter === 1 ? 0 : 0)); // no empty segments in lite version
        $offset += $len;
        if ($offset < $L) $cuts[] = $offset;
    }
    // Split message into segments
    $segments = [];
    $prev = 0;
    foreach ($cuts as $c) { $segments[] = substr($msg, $prev, $c - $prev); $prev = $c; }
    $segments[] = substr($msg, $prev);

    // 2) Permutation: Fisher-Yates with HMAC-CTR PRNG
    $perm = range(0, $k-1);
    for ($i = $k - 1; $i > 0; $i--) {
        $rnd = hmac_ctr_stream($Kperm, $nonce_logic, 1000 + $i, 4);
        $j = unpack('N', $rnd)[1] % ($i + 1);
        [$perm[$i], $perm[$j]] = [$perm[$j], $perm[$i]];
    }
    $S = [];
    foreach ($perm as $pi) { $S[] = $segments[$pi]; }

    // 3) Mixing: XOR-only per segment (educational)
    $S_mixed = [];
    for ($i = 0; $i < $k; $i++) {
        $seg = $S[$i];
        $len = strlen($seg);
        if ($len === 0) { $S_mixed[] = $seg; continue; }
        $ks = hmac_ctr_stream($Kmix, $nonce_logic, 2000 + $i, $len);
        $S_mixed[] = $seg ^ $ks;
    }

    // Concatenated C′
    $Cprime = implode('', $S_mixed);
    $lensP = array_map('strlen', $S_mixed);

    // 4) AD with protected logic
    $ad = $version
        . "|k:" . pack("N", $k)
        . "|cutsH:" . hash('sha256', implode(',', $cuts), true)
        . "|permH:" . hash('sha256', implode(',', $perm), true)
        . "|lensP:" . implode('', array_map(fn($n)=>pack('N',$n), $lensP));

    // 5) AEAD (use the same 96-bit nonce for GCM)
    [$C, $tag] = aes256gcm_encrypt($Kaead, $nonce_logic, $ad, $Cprime);

    return [
        "version" => $version,
        "k" => $k,
        "cuts" => $cuts,
        "perm" => $perm,
        "segment_lengths" => $lensP,
        "nonce_hex" => bin2hex($nonce_logic),
        "Cprime_hex" => bin2hex($Cprime),
        "ciphertext_hex" => bin2hex($C),
        "tag_hex" => bin2hex($tag),
        "ad_hex" => bin2hex($ad),
    ];
}

function logic_entropy_lite_decrypt($seed512, $nonce_logic, $enc) {
    $version = $enc["version"];
    $seedlen = strlen($seed512);
    if ($seedlen !== 64) throw new Exception("seed must be 512 bits (64 bytes)");
    $material = hkdf_sha256($seed512, "salt|$version", "info|$version", 32*4);
    $Kaead = substr($material, 96, 32);

    $C = hex2bin($enc["ciphertext_hex"]);
    $tag = hex2bin($enc["tag_hex"]);
    $ad = hex2bin($enc["ad_hex"]);
    $nonce = hex2bin($enc["nonce_hex"]);

    $Cprime = aes256gcm_decrypt($Kaead, $nonce, $ad, $C, $tag);
    if ($Cprime === false) throw new Exception("AEAD auth failed");
    return $Cprime; // In lite model we return C′ (mixed message)
}


// Reuse utilities from the previous snippet:
// hkdf_sha256, hmac_ctr_stream, aes256gcm_encrypt, aes256gcm_decrypt

function logic_entropy_lite_decrypt_full($seed512, $enc) {
    $version = $enc["version"];
    if (strlen($seed512) !== 64) throw new Exception("seed must be 512 bits (64 bytes)");

    // Re-derive keys
    $material = hkdf_sha256($seed512, "salt|$version", "info|$version", 32*4);
    $Kseg  = substr($material, 0, 32);
    $Kperm = substr($material, 32, 32);
    $Kmix  = substr($material, 64, 32);
    $Kaead = substr($material, 96, 32);

    // Inputs from the package
    $nonce = hex2bin($enc["nonce_hex"]);
    $ad    = hex2bin($enc["ad_hex"]);
    $C     = hex2bin($enc["ciphertext_hex"]);
    $tag   = hex2bin($enc["tag_hex"]);

    // 1) Validate AEAD and recover C′
    $Cprime = aes256gcm_decrypt($Kaead, $nonce, $ad, $C, $tag);
    if ($Cprime === false) throw new Exception("AEAD auth failed");

    // 2) Re-segment C′ using permuted lengths (lensP)
    $lensP = $enc["segment_lengths"];
    $k = $enc["k"];
    if (count($lensP) !== $k) throw new Exception("segment_lengths mismatch with k");

    $S_mixed = [];
    $offset = 0;
    foreach ($lensP as $len) {
        $S_mixed[] = substr($Cprime, $offset, $len);
        $offset += $len;
    }

    // 3) Undo XOR mixing per segment (same deterministic keystream)
    $S_unmixed = [];
    for ($i = 0; $i < $k; $i++) {
        $seg = $S_mixed[$i];
        $len = strlen($seg);
        if ($len === 0) { $S_unmixed[] = $seg; continue; }
        $ks = hmac_ctr_stream($Kmix, $nonce, 2000 + $i, $len);
        $S_unmixed[] = $seg ^ $ks; // XOR is its own inverse
    }

    // 4) Un-permute: apply inverse of perm to S_unmixed
    $perm = $enc["perm"]; // index array applied in encrypt
    if (count($perm) !== $k) throw new Exception("perm mismatch with k");

    // Build inverse: inv_perm[encrypted_pos] = original_pos
    $inv_perm = array_fill(0, $k, 0);
    for ($i = 0; $i < $k; $i++) {
        $inv_perm[$i] = array_search($i, $perm, true);
    }

    // Original order of segments (before permuting in encrypt)
    $segments_original = array_fill(0, $k, '');
    for ($i = 0; $i < $k; $i++) {
        $segments_original[$perm[$i]] = $S_unmixed[$i];
    }

    // 5) Re-assemble original message according to cuts
    // Cuts are cumulative offsets: reconstruct by concatenating in order 0..k-1
    $msg = implode('', $segments_original);

    return $msg;
}

// --- Usage example ---
// Assuming you already ran logic_entropy_lite_encrypt() with seed/nonce/msg:
$seed512 = random_bytes(64);
$nonce_logic = random_bytes(12);
$msg = "Encryption Engine Based on Logical Entropy";

$enc = logic_entropy_lite_encrypt($seed512, $nonce_logic, $msg);

$rec = logic_entropy_lite_decrypt_full($seed512, $enc);

// --- Clear output: original, ciphertext and reversal ---
echo "Original: {$msg}<br/>";
echo "Ciphertext (hex): {$enc['ciphertext_hex']}<br/>";
echo "Reversal (decrypted): {$rec}<br/>";
?>
