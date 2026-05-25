// Package seed управляет мастер-сидами HD-кошельков.
//
// Стандарты BIP-39 / BIP-32 (см. GUIDE.md, Урок 2 «HD-кошельки: один сид — много адресов»):
//
//   - BIP-39: 24 слова мнемоники (256 бит энтропии) → 64-байтовый seed через
//     PBKDF2-HMAC-SHA512 с 2048 итерациями.
//   - BIP-32: из 64 байт seed получаем master key + chain code. Из них
//     детерминированно выводятся дочерние ключи через HMAC-SHA512(chain_code,
//     parent_pubkey || index).
//
// Архитектурный принцип (GUIDE.md, Урок 2 «Почему ключи живут в отдельном сервисе»):
// материал ключей НИКОГДА не покидает signing-svc. Laravel оперирует только
// непрозрачной ссылкой (reference) — см. App\Modules\Address\Domain\ValueObject\HdSeedReference.
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

// Generate создаёт новую BIP-39 мнемонику с 256-битной энтропией (24 слова)
// и выводит из неё BIP-32 мастер-ключ (GUIDE.md, Урок 2).
//
// passphrase — необязательная BIP-39 passphrase ("" по умолчанию; см. BIP-39 §4).
// Если задана — становится частью seed, без неё восстановить кошелёк нельзя.
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
