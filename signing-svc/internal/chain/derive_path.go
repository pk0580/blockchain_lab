package chain

import (
	"fmt"
	"strconv"
	"strings"

	"github.com/tyler-smith/go-bip32"
)

// hardenedOffset — смещение 2^31, помечающее шаг деривации как hardened.
//
// Hardened шаг (BIP-32, см. GUIDE.md, Урок 2 «HD-кошельки»): дочерний ключ
// нельзя вывести только из публичного родителя — нужен приватный. Утечка
// одного pubkey не открывает «соседние» аккаунты. В пути это апостроф ('
// или 'h'/'H'), а внутри bip32 — добавление 0x80000000 к индексу.
const hardenedOffset uint32 = 0x80000000

// DerivePath проходит путь BIP-32 от мастер-ключа, например "m/44'/60'/0'/0/0".
//
// Поддерживаются нотации: "'" (стандарт BIP-32) и "h"/"H" (электрум-стиль) —
// обе помечают шаг как hardened. Возвращает дочерний ключ листа.
//
// Шаблоны путей для семейств — см. App\Modules\Address\Domain\Service\DerivationPathFactory:
//
//	Bitcoin (BIP-84): m/84'/0'/0'/0/{i}
//	EVM     (BIP-44): m/44'/60'/0'/0/{i}
//	Tron    (BIP-44): m/44'/195'/0'/0/{i}
func DerivePath(master *bip32.Key, path string) (*bip32.Key, error) {
	if master == nil {
		return nil, fmt.Errorf("nil master key")
	}
	parts := strings.Split(path, "/")
	if len(parts) == 0 || parts[0] != "m" {
		return nil, fmt.Errorf("path must start with 'm/': %q", path)
	}
	cur := master
	for _, p := range parts[1:] {
		if p == "" {
			return nil, fmt.Errorf("empty path segment in %q", path)
		}
		hardened := false
		if last := p[len(p)-1]; last == '\'' || last == 'h' || last == 'H' {
			hardened = true
			p = p[:len(p)-1]
		}
		n, err := strconv.ParseUint(p, 10, 32)
		if err != nil {
			return nil, fmt.Errorf("path segment %q: %w", p, err)
		}
		idx := uint32(n)
		if hardened {
			idx += hardenedOffset
		}
		cur, err = cur.NewChildKey(idx)
		if err != nil {
			return nil, fmt.Errorf("derive %q: %w", p, err)
		}
	}
	return cur, nil
}
