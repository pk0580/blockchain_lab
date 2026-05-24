package chain

import (
	"fmt"
	"strconv"
	"strings"

	"github.com/tyler-smith/go-bip32"
)

// hardenedOffset — это смещение 2^31, которое помечает этап вывода как hardened.
const hardenedOffset uint32 = 0x80000000

// DerivePath проходит путь BIP-32, такой как "m/44'/60'/0'/0/0", от мастер-ключа.
// Кавычка (') помечает hardened-этап. Возвращает дочерний ключ листа.
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
