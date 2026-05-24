# ADR 0004 — Defer `trustwallet/wallet-core`, ship pure-Go crypto first

- Status: Accepted
- Date: 2026-05-21
- Deciders: kritskiyp@gmail.com
- Supersedes part of: ADR 0002 (which committed to wallet-core)

## Context

ADR 0002 chose `trustwallet/wallet-core` as the signing library. Cost of using it in this project:

- It is a C++ library with bindings for Go via cgo.
- Building requires Bazel or CMake plus a clang/gcc toolchain. Adds ~600 MB to the Docker image, ~20 min to first build, slow incremental rebuilds.
- Cross-compilation between Alpine and glibc requires extra care; static linking inside Alpine + cgo is fiddly.
- We do not yet need the long tail of chains it supports (Solana, Cosmos, Cardano, …). The MVP is BTC + 2 EVM chains + Tron.

For a project where the codebase doubles as a textbook, a 600 MB build with C++ glue around our signing path is hostile to the reader.

## Decision

Build the Phase-2 signing service in **pure Go**, using widely-used libraries:

- `github.com/btcsuite/btcd/btcec/v2` — secp256k1 + Bitcoin keys.
- `github.com/btcsuite/btcd/btcutil` — Bitcoin address encoding (P2PKH / P2WPKH / Bech32).
- `github.com/ethereum/go-ethereum/crypto` — Ethereum / EVM secp256k1 ops and keccak-256 hashing.
- `github.com/tyler-smith/go-bip32` + `github.com/tyler-smith/go-bip39` — HD wallets and mnemonics.
- Tron address format = `0x41 + last20(keccak256(uncompressed_pubkey[1:]))` + base58check; we implement this inline.

`trustwallet/wallet-core` may be revisited in **Phase 9+** if and when we need:

- Long-tail chain support (Solana, Cardano, Cosmos, etc.).
- Battle-tested protobuf-defined transaction encoders.

The signing service exposes a stable HTTP API. The implementation behind that API can be swapped without touching Laravel.

## Consequences

### Positive

- Statically-linked Go binary, single-step build, no C++ toolchain.
- Tiny image (~25 MB final stage).
- Every line of cryptographic logic is readable Go (educational goal).
- Identical API surface as if we used wallet-core, so a future swap is mechanical.

### Negative

- We re-encode address formats ourselves for Bitcoin Bech32 and Tron base58check. We pull these from established libraries (no hand-rolled hashing), but the surface area we own is larger than with wallet-core.
- No out-of-the-box support for new L1s (Solana, Move-based chains, etc.). New families = new Go code per family. Mitigated because the MVP is bounded.

### Neutral

- The Laravel-side HTTP client and the signing service contract are unchanged from ADR 0002. The contract is the stable seam.
