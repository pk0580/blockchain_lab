// Package playground содержит ephemeral-эндпоинты для образовательной UI.
//
// В отличие от обычных endpoint'ов /v1/seeds + /v1/addresses/derive,
// playground НЕ сохраняет seed'ы и возвращает private/public key материал в
// открытом виде. Это сознательное нарушение security-границы — только для
// учебной демонстрации, никогда не для реальных средств.
package playground

import (
	"crypto/sha256"
	"encoding/hex"
	"errors"
	"fmt"

	"github.com/blockchain-lab/signing-svc/internal/chain"
	"github.com/btcsuite/btcd/btcec/v2"
	"github.com/btcsuite/btcd/btcutil"
	"github.com/btcsuite/btcd/chaincfg"
	ethcommon "github.com/ethereum/go-ethereum/common"
	"github.com/ethereum/go-ethereum/crypto"
	"github.com/tyler-smith/go-bip32"
	"github.com/tyler-smith/go-bip39"
)

// Keypair — материал, возвращаемый playground'у. Все hex-поля в lower-case.
type Keypair struct {
	Mnemonic              string `json:"mnemonic"`
	DerivationPath        string `json:"derivation_path"`
	PrivateKeyHex         string `json:"private_key_hex"`
	PublicKeyCompressed   string `json:"public_key_compressed_hex"`
	PublicKeyUncompressed string `json:"public_key_uncompressed_hex"`
	BitcoinAddress        string `json:"bitcoin_address"`
	EthereumAddress       string `json:"ethereum_address"`
	TronAddress           string `json:"tron_address"`
}

// GenerateKeypair создаёт mnemonic + child-ключ по пути BIP-44 и возвращает
// производные данные для трёх семейств. Если mnemonic пустой — генерирует
// новый со 128 битами энтропии (12 слов; playground'у не нужны 256-битные
// seed'ы как у production).
func GenerateKeypair(mnemonic string) (*Keypair, error) {
	if mnemonic == "" {
		entropy, err := bip39.NewEntropy(128)
		if err != nil {
			return nil, fmt.Errorf("entropy: %w", err)
		}
		mnemonic, err = bip39.NewMnemonic(entropy)
		if err != nil {
			return nil, fmt.Errorf("mnemonic: %w", err)
		}
	} else if !bip39.IsMnemonicValid(mnemonic) {
		return nil, errors.New("invalid mnemonic")
	}

	rawSeed := bip39.NewSeed(mnemonic, "")
	master, err := bip32.NewMasterKey(rawSeed)
	if err != nil {
		return nil, fmt.Errorf("master key: %w", err)
	}

	const path = "m/44'/0'/0'/0/0"
	child, err := chain.DerivePath(master, path)
	if err != nil {
		return nil, fmt.Errorf("derive: %w", err)
	}

	privBytes := child.Key
	_, pub := btcec.PrivKeyFromBytes(privBytes)
	pubCompressed := pub.SerializeCompressed()
	pubUncompressed := pub.SerializeUncompressed()

	btcAddr, err := bitcoinSegwitAddress(pubCompressed)
	if err != nil {
		return nil, fmt.Errorf("btc address: %w", err)
	}
	ethAddr := ethereumAddress(pubUncompressed)
	tronAddr := tronAddress(pubUncompressed)

	return &Keypair{
		Mnemonic:              mnemonic,
		DerivationPath:        path,
		PrivateKeyHex:         hex.EncodeToString(privBytes),
		PublicKeyCompressed:   hex.EncodeToString(pubCompressed),
		PublicKeyUncompressed: hex.EncodeToString(pubUncompressed),
		BitcoinAddress:        btcAddr,
		EthereumAddress:       ethAddr,
		TronAddress:           tronAddr,
	}, nil
}

func bitcoinSegwitAddress(pubCompressed []byte) (string, error) {
	hash160 := btcutil.Hash160(pubCompressed)
	addr, err := btcutil.NewAddressWitnessPubKeyHash(hash160, &chaincfg.MainNetParams)
	if err != nil {
		return "", err
	}
	return addr.EncodeAddress(), nil
}

func ethereumAddress(pubUncompressed []byte) string {
	if len(pubUncompressed) != 65 {
		return ""
	}
	digest := crypto.Keccak256(pubUncompressed[1:])
	return ethcommon.BytesToAddress(digest[12:]).Hex()
}

func tronAddress(pubUncompressed []byte) string {
	if len(pubUncompressed) != 65 {
		return ""
	}
	digest := crypto.Keccak256(pubUncompressed[1:])
	payload := append([]byte{0x41}, digest[12:]...)
	return base58CheckEncode(payload)
}

func base58CheckEncode(payload []byte) string {
	first := sha256.Sum256(payload)
	second := sha256.Sum256(first[:])
	full := append([]byte(nil), payload...)
	full = append(full, second[:4]...)
	return base58Encode(full)
}

const base58Alphabet = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz"

func base58Encode(b []byte) string {
	zeros := 0
	for zeros < len(b) && b[zeros] == 0 {
		zeros++
	}
	num := append([]byte(nil), b...)
	encoded := make([]byte, 0, len(b)*138/100+1)
	for {
		allZero := true
		for _, x := range num {
			if x != 0 {
				allZero = false
				break
			}
		}
		if allZero {
			break
		}
		var rem int
		for i := range num {
			acc := int(num[i]) + rem*256
			num[i] = byte(acc / 58)
			rem = acc % 58
		}
		encoded = append(encoded, base58Alphabet[rem])
	}
	for i := 0; i < zeros; i++ {
		encoded = append(encoded, base58Alphabet[0])
	}
	for i, j := 0, len(encoded)-1; i < j; i, j = i+1, j-1 {
		encoded[i], encoded[j] = encoded[j], encoded[i]
	}
	return string(encoded)
}
