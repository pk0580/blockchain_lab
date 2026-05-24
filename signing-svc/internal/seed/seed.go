// Package seed управляет мастер-сидами HD-кошельков. Сиды генерируются из
// мнемоники BIP-39, сохраняются в зашифрованном виде и адресуются по непрозрачной
// ссылке (UUID-подобный идентификатор) — никогда по мнемонике или материалу ключа.
package seed

import (
	"errors"
	"fmt"

	"github.com/tyler-smith/go-bip32"
	"github.com/tyler-smith/go-bip39"
)

// Seed — это необработанный 64-байтовый материал сида BIP-39 плюс стабильный идентификатор ссылки.
// Reference — это то, что хранят вызывающие стороны; Master никогда не возвращается за
// границы процесса.
type Seed struct {
	Reference string
	Mnemonic  string
	Master    *bip32.Key
}

// Generate создает новую мнемонику с 256-битной энтропией и выводит мастер-ключ.
// `passphrase` — это парольная фраза BIP-39 (используйте "" по умолчанию; см. BIP-39 §4).
func Generate(reference, passphrase string) (*Seed, error) {
	if reference == "" {
		return nil, errors.New("seed reference is required")
	}
	entropy, err := bip39.NewEntropy(256)
	if err != nil {
		return nil, fmt.Errorf("entropy: %w", err)
	}
	mnemonic, err := bip39.NewMnemonic(entropy)
	if err != nil {
		return nil, fmt.Errorf("mnemonic: %w", err)
	}
	return fromMnemonic(reference, mnemonic, passphrase)
}

// FromMnemonic восстанавливает Seed из известной мнемоники — полезно для тестов
// с опубликованными векторами BIP-39.
func FromMnemonic(reference, mnemonic, passphrase string) (*Seed, error) {
	return fromMnemonic(reference, mnemonic, passphrase)
}

func fromMnemonic(reference, mnemonic, passphrase string) (*Seed, error) {
	if !bip39.IsMnemonicValid(mnemonic) {
		return nil, errors.New("invalid mnemonic")
	}
	rawSeed := bip39.NewSeed(mnemonic, passphrase)
	master, err := bip32.NewMasterKey(rawSeed)
	if err != nil {
		return nil, fmt.Errorf("master key: %w", err)
	}
	return &Seed{
		Reference: reference,
		Mnemonic:  mnemonic,
		Master:    master,
	}, nil
}
