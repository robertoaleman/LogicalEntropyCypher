# Logical Entropy Cypher v1.0
Encryption Engine Based on Logical Entropy
<br> A practical and educational approach to wrapping standard ciphers (such as AES‑256‑GCM)  with a layer of 'logical entropy' that adds structural variability, configurable diffusion,  and authentication of the logic itself. It is designed to integrate without breaking compatibility or infrastructure.
<b>Author: Roberto Aleman, ventics.com</b> 
<br><b>License: GNU GPL v3</b>

<h1>Encryption Engine Based on Logical Entropy</h1>

This document presents a practical, educational approach to wrapping standard ciphers (such as AES-256-GCM) with a layer of "logical entropy" that adds structural variability, configurable diffusion and authentication of the internal logic. It is designed to integrate without breaking compatibility or existing infrastructure.
<div></div>
<h3>Concept of Logical Entropy and Its Relation to Cryptography</h3>
<ul>
 	<li>Idea central Logical entropy provides additional entropy by dynamically segmenting the plaintext, applying an unpredictable permutation, and performing a reversible mixing step (XOR or Feistel) before the AEAD stage.</li>
 	<li>Relationship to modern cryptography The logical layer operates on top of a proven cryptographic engine (AES-GCM), adding structural complexity without weakening the underlying security.</li>
 	<li>Purpose Compatibility and reinforcement rather than replacement. It enables an orderly migration path to post?quantum schemes while preserving the same architectural model.</li>
</ul>
<div></div>
<h3>Architecture and Encryption Flow</h3>
<h4>Inputs and Key Derivations</h4>
<ul>
 	<li><strong>Inputs</strong>
<ul>
 	<li><strong>Seed</strong> 512 bits as the master secret.</li>
 	<li><strong>Logical nonce</strong> 96 bits for deterministic derivations and AEAD.</li>
 	<li><strong>Message</strong> arbitrary bytes.</li>
</ul>
</li>
 	<li><strong>Derivations using HKDF</strong> and domain separation
<ul>
 	<li><strong>Kseg</strong> segmentation domain key.</li>
 	<li><strong>Kperm</strong> permutation domain key.</li>
 	<li><strong>Kmix</strong> mixing/keystream domain key.</li>
 	<li><strong>Kaead</strong> AEAD key for AES-256-GCM.</li>
</ul>
</li>
</ul>
<h4>Steps of the Logical Entropy Layer</h4>
<ol start="1">
 	<li><strong>Segmentation</strong>
<ul>
 	<li>Deterministic cuts derived from Kseg and the logical nonce produce k segments with no empty pieces.</li>
</ul>
</li>
 	<li><strong>Permutation</strong>
<ul>
 	<li>Fisher-Yates shuffle using an HMAC-CTR PRNG (Kperm + nonce) yields a reproducible but unpredictable order.</li>
</ul>
</li>
 	<li><strong>Mixing (lite mode)</strong>
<ul>
 	<li>XOR per segment with a keystream derived from Kmix and the nonce. The operation is reversible.</li>
</ul>
</li>
 	<li><strong>AEAD</strong>
<ul>
 	<li>AES-256-GCM encrypts the concatenated C?.</li>
 	<li>Associated Data includes version, k, hash of cuts, hash of permutation and permuted segment lengths to protect the internal logic.</li>
</ul>
</li>
</ol>
<div></div>
<h3>Comparative Table and Practical Value</h3>
<div>
<div>
<table>
<thead>
<tr>
<th>Aspect</th>
<th>AES?256?GCM</th>
<th>Logic Entropy Lite</th>
</tr>
</thead>
<tbody>
<tr>
<td>Source of entropy</td>
<td>Key and nonce</td>
<td>Key, nonce, cuts and permutation</td>
</tr>
<tr>
<td>Diffusion</td>
<td>High and fixed</td>
<td>Configurable (XOR or Feistel)</td>
</tr>
<tr>
<td>Authentication scope</td>
<td>Data and optional AD</td>
<td>Data and internal logic authenticated</td>
</tr>
<tr>
<td>Compatibility</td>
<td>Standard</td>
<td>Wrapping, interoperable</td>
</tr>
<tr>
<td>Post-quantum posture</td>
<td>~128 bits effective</td>
<td>Similar plus structural complexity</td>
</tr>
<tr>
<td>Migration to PQC AEAD</td>
<td>Requires replacement</td>
<td>Logical layer reusable with new AEAD</td>
</tr>
</tbody>
</table>
</div>
<div></div>
</div>
Value statement The logical entropy layer forces an attacker to reconstruct and authenticate the internal structure before meaningful manipulation is possible. It increases the practical effort required to exploit real systems while preserving interoperability with standard AEAD primitives.
<h3>Installation and Usage</h3>
<h4>Requirements</h4>
<ul>
 	<li>PHP 8+ with OpenSSL enabled.</li>
 	<li>Standard extensions: hash, openssl.</li>
</ul>
<h4>Installation</h4>
<ul>
 	<li>Quick option: copy the <code>logic_entropy_lite.php</code> file into your project.</li>
 	<li>Distribution: publish as a Composer package if you plan to share or maintain it.</li>
</ul>
<h2>Basic usage</h2>
<ul>
 	<li>
<h2>Encrypt (lite) example</h2>
</li>
</ul>
<code>&lt;?php</code>
<code>require 'logic_entropy_lite.php';</code>

<code>$seed512 = random_bytes(64); // 512 bits</code>
<code>$nonce_logic = random_bytes(12); // 96 bits</code>
<code>$msg = "Hola mundo";</code>

<code>$enc = logic_entropy_lite_encrypt($seed512, $nonce_logic, $msg);</code>

<code>echo "Original: {$msg}\n";</code>
<code>echo "Ciphertext (hex): {$enc['ciphertext_hex']}\n";</code>
<code>echo "Tag (hex): {$enc['tag_hex']}\n";</code>
<h2>Decrypt (full) example</h2>
&nbsp;
<code>&lt;?php</code>
<code>$rec = logic_entropy_lite_decrypt_full($seed512, $enc);</code>
<code>echo "Reversal (decrypted): {$rec}\n";</code>
&nbsp;
<h4>Production recommendations</h4>
<ul>
 	<li>Never reuse a nonce with the same AEAD key.</li>
 	<li>Version HKDF domains and AD format to support future extensions.</li>
 	<li>Optional telemetry: measure diffusion (Hamming distance) to compare modes.</li>
 	<li>Parallelization: distribute segments to workers in XOR-only mode; use intra?round parallelism for Feistel in the full model.</li>
</ul>
<h3>Conclusion</h3>
<ul>
 	<li>What it delivers A logical entropy layer that strengthens security by treating the message structure as entropy and protecting it via AEAD.</li>
 	<li>Why it matters It allows evolution of cryptographic deployments without a disruptive reset of infrastructure and remains aligned with post?quantum readiness by retaining a symmetric core and being portable to future AEAD primitives.</li>
 	<li>Real world applicability Suitable for firmware updates on chips, web services, and multi-language deployments. The code is simple, auditable and portable</li>
  <li>The author continues developing the concept of encryption based on logical entropy in a larger project that he is carrying out on his own. This document shows how part of the project allows an existing encryption to be wrapped and given an additional layer of security and integrity.</li>
</ul>
&nbsp;

<p><b>Legal Notice and Disclaimer:</b></p>
The code and documentation are published as-is, without any express or implied warranties regarding suitability, safety, or fitness for a particular purpose. The material is offered solely for educational purposes and as a conceptual proposal for a larger project under development. The user is solely responsible for the implementation, testing, deployment, and use of the code or any derivatives. The author and collaborators assume no responsibility for damages, losses, vulnerabilities, misuse, or consequences resulting from the use of this material by third parties. By using this project, you agree to assume all associated risks and to maintain proper security and auditing practices.
