# ADR 0002 — Extract transaction signing into a Go service (wallet-core)

- Status: Accepted (implementation in Phase 2)
- Date: 2026-05-21
- Deciders: kritskiyp@gmail.com

## Context

Signing a blockchain transaction means holding a private key in process memory at the moment the signature is computed. Anything that compromises the signing process compromises every key it has ever touched.

Two cross-cutting constraints push us toward isolation:

1. **Surface area.** A Laravel application loads tens of vendor packages, dozens of facades, a full web server. Each is a potential vector. Keys must not live in that process.
2. **Cryptographic primitives.** We need BTC (P2WPKH, P2TR), Ethereum (legacy + EIP-1559 + EIP-2930), Tron, and EVM L2s. In PHP, this is fragmented across `BitWasp/bitcoin-php`, `kornrunner/ethereum-offline-raw-tx`, `web3p/web3.php`, `iexbase/tron-api`, `simplito/elliptic-php`. Each is maintained by a different sub-community; coverage of newer chains and edge cases (segwit, EIP-1559, Tron's encoding peculiarities) is uneven.

We need a single, audited, well-maintained library that covers everything we plan to support and everything we will reasonably need.

## Decision

- All key material lives in a dedicated **Go service** (`signing-svc`), built around [`trustwallet/wallet-core`](https://github.com/trustwallet/wallet-core).
- Laravel calls it over **mTLS HTTP** (gRPC optional later). API surface is narrow: derive address, validate address, sign raw transaction, decode raw transaction.
- Master encryption key in **KMS / HashiCorp Vault** (SoftHSM for local dev). Per-seed DEK envelope encryption: KMS encrypts a data key, the data key encrypts the seed at rest. Seeds never appear in plaintext on disk.
- Laravel stores **references** (`hd_seed_ref`, `key_ref`), never key material. Every signing operation is recorded in an append-only audit log on the Go side.
- The signing service has **no access to the application database**. It cannot enumerate users, balances, or addresses on its own. It receives a request, signs, returns.

## Why Go (and not PHP, not Node, not Rust)

- `wallet-core` is the canonical multi-coin crypto library: it powers Trust Wallet (millions of users), is C++ with bindings for Go / Swift / Kotlin / Java, and is actively maintained. Coverage includes the long tail of L2s and new chains.
- Go gives us cheap concurrency, a static binary, fast cold-start, mature TLS / KMS SDKs, and a minimal runtime — the right shape for a security-critical service.
- PHP wallet-core bindings do not exist with comparable coverage. Rolling our own multi-chain PHP signer is a project larger than this one.
- Node alternatives (`ethers.js`, `bitcoinjs-lib`) are great but cover a narrower set of chains and the npm dependency surface is its own risk story.

## Consequences

### Positive

- Compromise of the Laravel app does not yield private keys. A directory traversal in a controller leaks data, not keys.
- One library, one canonical chain coverage. Adding a new chain often means a new `coin_type` constant on the Go side, no new crypto code.
- Audit-friendly: signing events live in one append-only log on one host.

### Negative

- New runtime (Go) and new deployment artefact.
- Network hop for every signing operation (Laravel → Go). Latency ≈ 1–3 ms in-cluster; acceptable for withdrawal throughput.
- cgo overhead (wallet-core is C++). Batch signing endpoint mitigates if we ever need >100 sigs/sec.
- Internal-API contract has to evolve carefully — `signing-svc` runs out-of-step with Laravel during deploys, so all RPCs must be backwards-compatible within a deploy window.

### Neutral

- Phase 2 placeholder is currently an `alpine:3.20` container with `sleep infinity` so the rest of the stack composes cleanly while Phase 2 is built.
