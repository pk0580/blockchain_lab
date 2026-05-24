package playground

import (
	"bytes"
	"encoding/hex"
	"fmt"
	"strings"

	"github.com/btcsuite/btcd/wire"
	ethcommon "github.com/ethereum/go-ethereum/common"
	ethtypes "github.com/ethereum/go-ethereum/core/types"
)

// BitcoinTxInput — одна input'а декодированной BTC-транзакции.
type BitcoinTxInput struct {
	PrevTxID  string `json:"prev_txid"`
	PrevVout  uint32 `json:"prev_vout"`
	ScriptSig string `json:"script_sig_hex"`
	Sequence  uint32 `json:"sequence"`
}

// BitcoinTxOutput — один output BTC-транзакции (value в сатоши).
type BitcoinTxOutput struct {
	ValueSats   int64  `json:"value_sats"`
	ScriptPkHex string `json:"script_pubkey_hex"`
}

// BitcoinDecoded — структурированное представление BTC tx hex.
type BitcoinDecoded struct {
	Version   int32             `json:"version"`
	Locktime  uint32            `json:"locktime"`
	Inputs    []BitcoinTxInput  `json:"inputs"`
	Outputs   []BitcoinTxOutput `json:"outputs"`
	TxIDHex   string            `json:"txid_hex"`
	SizeBytes int               `json:"size_bytes"`
}

// EthereumDecoded — структурированное представление EVM tx hex (legacy / EIP-1559).
type EthereumDecoded struct {
	TxType    string `json:"tx_type"`
	ChainID   string `json:"chain_id"`
	Nonce     uint64 `json:"nonce"`
	GasLimit  uint64 `json:"gas_limit"`
	GasPrice  string `json:"gas_price_wei"`
	MaxFee    string `json:"max_fee_per_gas_wei"`
	MaxTipFee string `json:"max_priority_fee_per_gas_wei"`
	ToHex     string `json:"to"`
	ValueWei  string `json:"value_wei"`
	DataHex   string `json:"data_hex"`
	Hash      string `json:"hash"`
}

// DecodeBitcoin парсит hex-encoded BTC raw transaction.
func DecodeBitcoin(rawHex string) (*BitcoinDecoded, error) {
	cleaned := strings.TrimPrefix(strings.ToLower(rawHex), "0x")
	raw, err := hex.DecodeString(cleaned)
	if err != nil {
		return nil, fmt.Errorf("hex: %w", err)
	}
	var tx wire.MsgTx
	if err := tx.Deserialize(bytes.NewReader(raw)); err != nil {
		return nil, fmt.Errorf("deserialize: %w", err)
	}

	inputs := make([]BitcoinTxInput, 0, len(tx.TxIn))
	for _, in := range tx.TxIn {
		inputs = append(inputs, BitcoinTxInput{
			PrevTxID:  in.PreviousOutPoint.Hash.String(),
			PrevVout:  in.PreviousOutPoint.Index,
			ScriptSig: hex.EncodeToString(in.SignatureScript),
			Sequence:  in.Sequence,
		})
	}
	outputs := make([]BitcoinTxOutput, 0, len(tx.TxOut))
	for _, out := range tx.TxOut {
		outputs = append(outputs, BitcoinTxOutput{
			ValueSats:   out.Value,
			ScriptPkHex: hex.EncodeToString(out.PkScript),
		})
	}

	return &BitcoinDecoded{
		Version:   tx.Version,
		Locktime:  tx.LockTime,
		Inputs:    inputs,
		Outputs:   outputs,
		TxIDHex:   tx.TxHash().String(),
		SizeBytes: len(raw),
	}, nil
}

// DecodeEthereum парсит hex-encoded EVM raw transaction (любой тип: legacy /
// access list / EIP-1559).
func DecodeEthereum(rawHex string) (*EthereumDecoded, error) {
	cleaned := strings.TrimPrefix(strings.ToLower(rawHex), "0x")
	raw, err := hex.DecodeString(cleaned)
	if err != nil {
		return nil, fmt.Errorf("hex: %w", err)
	}
	var tx ethtypes.Transaction
	if err := tx.UnmarshalBinary(raw); err != nil {
		return nil, fmt.Errorf("unmarshal: %w", err)
	}

	to := ""
	if tx.To() != nil {
		to = tx.To().Hex()
	}
	chainIDStr := "0"
	if tx.ChainId() != nil {
		chainIDStr = tx.ChainId().String()
	}

	d := &EthereumDecoded{
		TxType:   txTypeName(tx.Type()),
		ChainID:  chainIDStr,
		Nonce:    tx.Nonce(),
		GasLimit: tx.Gas(),
		ToHex:    to,
		ValueWei: tx.Value().String(),
		DataHex:  "0x" + hex.EncodeToString(tx.Data()),
		Hash:     tx.Hash().Hex(),
	}
	switch tx.Type() {
	case ethtypes.LegacyTxType, ethtypes.AccessListTxType:
		d.GasPrice = tx.GasPrice().String()
	case ethtypes.DynamicFeeTxType:
		d.MaxFee = tx.GasFeeCap().String()
		d.MaxTipFee = tx.GasTipCap().String()
	}
	return d, nil
}

func txTypeName(t uint8) string {
	switch t {
	case ethtypes.LegacyTxType:
		return "legacy"
	case ethtypes.AccessListTxType:
		return "access_list"
	case ethtypes.DynamicFeeTxType:
		return "dynamic_fee_eip1559"
	default:
		return fmt.Sprintf("unknown_type_%d", t)
	}
}

// EnsureNotEmpty defensive guard: dec error paths иногда возвращают struct'у
// с пустыми address полями — оставляем как-есть, фронтенд показывает "—".
var _ = ethcommon.Address{}
