// Package chain содержит логику HD-вывода и валидации адресов для каждого семейства сетей.
//
// Каждое семейство сетей реализует интерфейсы Derivator и Validator.
// Новое семейство = новый файл в этом пакете + регистрация в Registry().
package chain

import (
	"fmt"
	"strings"

	"github.com/tyler-smith/go-bip32"
)

// Family идентифицирует семейство сетей.
type Family string

const (
	FamilyBitcoin Family = "bitcoin"
	FamilyEvm     Family = "evm"   // Ethereum, Polygon, Arbitrum, …
	FamilyTron    Family = "tron"
)

// FamilyFromString преобразует строку в значение Family (регистронезависимо).
func FamilyFromString(v string) (Family, error) {
	switch strings.ToLower(v) {
	case "bitcoin":
		return FamilyBitcoin, nil
	case "evm":
		return FamilyEvm, nil
	case "tron":
		return FamilyTron, nil
	default:
		return "", fmt.Errorf("unknown chain family %q", v)
	}
}

// Derivator превращает дочерний HD-ключ в адрес конкретной сети.
type Derivator interface {
	Family() Family
	Address(child *bip32.Key) (string, error)
}

// Validator определяет, является ли строка адреса корректно сформированной для своего семейства.
type Validator interface {
	Family() Family
	IsValid(address string) bool
}

// Registry возвращает настроенный derivator + validator для каждого поддерживаемого семейства.
type Registry struct {
	derivators map[Family]Derivator
	validators map[Family]Validator
}

// NewRegistry подключает реализации по умолчанию для каждого семейства, поставляемые
// вместе с сервисом подписи.
func NewRegistry() *Registry {
	r := &Registry{
		derivators: make(map[Family]Derivator),
		validators: make(map[Family]Validator),
	}
	r.register(newBitcoinAdapter())
	r.register(newEvmAdapter())
	r.register(newTronAdapter())
	return r
}

func (r *Registry) register(a interface {
	Derivator
	Validator
}) {
	r.derivators[a.Family()] = a
	r.validators[a.Family()] = a
}

// Derivator возвращает дериватор для семейства.
func (r *Registry) Derivator(f Family) (Derivator, error) {
	d, ok := r.derivators[f]
	if !ok {
		return nil, fmt.Errorf("no derivator for family %q", f)
	}
	return d, nil
}

// Validator возвращает валидатор для семейства.
func (r *Registry) Validator(f Family) (Validator, error) {
	v, ok := r.validators[f]
	if !ok {
		return nil, fmt.Errorf("no validator for family %q", f)
	}
	return v, nil
}
