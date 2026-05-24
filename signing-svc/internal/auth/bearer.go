// Package auth предоставляет middleware для токена Bearer. Фаза 2 использует общий
// секрет через обычный HTTP внутри сети Docker; Фаза 7 обновит это до mTLS.
package auth

import (
	"crypto/subtle"
	"net/http"
	"strings"
)

const headerPrefix = "Bearer "

// BearerMiddleware отклоняет запросы, которые не содержат ожидаемый токен в
// заголовке Authorization. Сравнение выполняется за константное время.
func BearerMiddleware(expected string) func(http.Handler) http.Handler {
	expectedBytes := []byte(expected)
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			h := r.Header.Get("Authorization")
			if !strings.HasPrefix(h, headerPrefix) {
				http.Error(w, `{"error":"missing_or_malformed_authorization"}`, http.StatusUnauthorized)
				return
			}
			got := strings.TrimPrefix(h, headerPrefix)
			if subtle.ConstantTimeCompare([]byte(got), expectedBytes) != 1 {
				http.Error(w, `{"error":"invalid_token"}`, http.StatusUnauthorized)
				return
			}
			next.ServeHTTP(w, r)
		})
	}
}
