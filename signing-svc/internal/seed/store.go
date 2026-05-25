package seed

import (
	"crypto/rand"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"regexp"

	"golang.org/x/crypto/chacha20poly1305"
)

// Store сохраняет зашифрованные сиды.
type Store interface {
	Save(s *Seed) error
	Load(reference string) (*Seed, error)
	Exists(reference string) (bool, error)
}

// FileStore хранит зашифрованные мнемоники в файловой системе.
//
// Формат файла <dir>/<reference>.sealed — JSON-конверт:
//
//	{"v":1, "nonce":"<bytes>", "ciphertext":"<bytes>"}
//
// AEAD: XChaCha20-Poly1305 с 32-байтовым ключом из конфига.
// Reference используется как `additional data` (AAD) — это связывает шифротекст
// с именем файла: если файл переименуют, расшифровка упадёт на проверке тега.
//
// Это и есть то самое «защищённое хранилище мнемоники», на которое ссылается
// App\Modules\Address\Domain\ValueObject\HdSeedReference (GUIDE.md, Урок 2
// «Ссылка на ключ»: «карточка в блокноте про мешок в банковском сейфе»).
type FileStore struct {
	dir string
	key []byte
}

// разрешать только безопасные символы имени файла в ссылке (reference)
var referenceRegexp = regexp.MustCompile(`^[a-zA-Z0-9_-]{4,64}$`)

type envelope struct {
	Version    int    `json:"v"`
	Nonce      []byte `json:"nonce"`
	Ciphertext []byte `json:"ciphertext"`
}

// NewFileStore проверяет ключ шифрования и гарантирует, что директория
// хранения существует с правами 0700 (только владелец процесса).
//
// ⚠️ Длина ключа = chacha20poly1305.KeySize (32 байта). Подсунутый «короткий»
// ключ из конфига — это утечка безопасности уровня «весь сейф откроется первой
// попавшейся отмычкой», поэтому проваливаемся явно на старте.
func NewFileStore(dir string, key []byte) (*FileStore, error) {
	if len(key) != chacha20poly1305.KeySize {
		return nil, fmt.Errorf("FileStore: encryption key must be %d bytes, got %d",
			chacha20poly1305.KeySize, len(key))
	}
	if err := os.MkdirAll(dir, 0o700); err != nil {
		return nil, fmt.Errorf("FileStore: mkdir %s: %w", dir, err)
	}
	return &FileStore{dir: dir, key: key}, nil
}

// Save шифрует мнемонику и атомарно записывает файл <reference>.sealed.
//
// Атомарность через write-tmp + rename — гарантия, что параллельный Load() не
// прочитает наполовину записанный файл. Права 0600: только владелец процесса.
//
// AAD = reference: AEAD-тег связывает шифротекст с именем файла, поэтому
// злоумышленник не может «перевесить» один .sealed на другое имя, чтобы
// подсунуть чужую мнемонику под нужный reference.
func (s *FileStore) Save(seed *Seed) error {
	if !referenceRegexp.MatchString(seed.Reference) {
		return errors.New("FileStore: invalid seed reference")
	}
	aead, err := chacha20poly1305.NewX(s.key)
	if err != nil {
		return fmt.Errorf("FileStore: aead: %w", err)
	}
	// XChaCha20 nonce — 24 байта, безопасен случайный (вероятность коллизии
	// для миллиардов сообщений пренебрежимо мала).
	nonce := make([]byte, aead.NonceSize())
	if _, err := io.ReadFull(rand.Reader, nonce); err != nil {
		return fmt.Errorf("FileStore: nonce: %w", err)
	}
	// Seal: ciphertext = encrypt(mnemonic) || tag(reference).
	ciphertext := aead.Seal(nil, nonce, []byte(seed.Mnemonic), []byte(seed.Reference))

	payload, err := json.Marshal(envelope{Version: 1, Nonce: nonce, Ciphertext: ciphertext})
	if err != nil {
		return fmt.Errorf("FileStore: marshal: %w", err)
	}

	path := filepath.Join(s.dir, seed.Reference+".sealed")
	tmpPath := path + ".tmp"
	// Шаг 1: записать во временный файл с правами 0600.
	if err := os.WriteFile(tmpPath, payload, 0o600); err != nil {
		return fmt.Errorf("FileStore: write tmp: %w", err)
	}
	// Шаг 2: атомарный rename — POSIX гарантирует, что rename внутри одной
	// файловой системы происходит за одну операцию (либо есть, либо нет).
	if err := os.Rename(tmpPath, path); err != nil {
		return fmt.Errorf("FileStore: rename: %w", err)
	}
	return nil
}

// Load считывает запечатанный сид, расшифровывает его и восстанавливает мастер-ключ.
func (s *FileStore) Load(reference string) (*Seed, error) {
	if !referenceRegexp.MatchString(reference) {
		return nil, errors.New("FileStore: invalid seed reference")
	}
	path := filepath.Join(s.dir, reference+".sealed")
	payload, err := os.ReadFile(path)
	if err != nil {
		return nil, fmt.Errorf("FileStore: read %s: %w", path, err)
	}
	var env envelope
	if err := json.Unmarshal(payload, &env); err != nil {
		return nil, fmt.Errorf("FileStore: unmarshal: %w", err)
	}
	if env.Version != 1 {
		return nil, fmt.Errorf("FileStore: unsupported envelope version %d", env.Version)
	}
	aead, err := chacha20poly1305.NewX(s.key)
	if err != nil {
		return nil, fmt.Errorf("FileStore: aead: %w", err)
	}
	plaintext, err := aead.Open(nil, env.Nonce, env.Ciphertext, []byte(reference))
	if err != nil {
		return nil, fmt.Errorf("FileStore: open: %w", err)
	}
	return FromMnemonic(reference, string(plaintext), "")
}

// Exists сообщает, присутствует ли запечатанный сид с этой ссылкой.
func (s *FileStore) Exists(reference string) (bool, error) {
	if !referenceRegexp.MatchString(reference) {
		return false, errors.New("FileStore: invalid seed reference")
	}
	path := filepath.Join(s.dir, reference+".sealed")
	if _, err := os.Stat(path); err == nil {
		return true, nil
	} else if errors.Is(err, os.ErrNotExist) {
		return false, nil
	} else {
		return false, err
	}
}
