#!/usr/bin/env bash
# =====================================================================
# Menerapkan metadata Hasura: melacak (track) tabel artists & concerts
# beserta relasinya, melalui Metadata API.
# Idempotent -> aman dijalankan berulang kali (replace_metadata).
# =====================================================================
set -euo pipefail

HASURA_URL="${HASURA_URL:-http://localhost:8080}"
ADMIN_SECRET="${HASURA_ADMIN_SECRET:-myadminsecret}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "==> Menunggu Hasura siap di ${HASURA_URL} ..."
for i in $(seq 1 60); do
  if curl -sf "${HASURA_URL}/healthz" >/dev/null 2>&1; then
    echo "    Hasura siap."
    break
  fi
  if [ "${i}" -eq 60 ]; then
    echo "    ERROR: Timeout menunggu Hasura. Pastikan 'docker compose up -d' sudah berjalan." >&2
    exit 1
  fi
  sleep 2
done

echo "==> Menerapkan metadata (track tabel + relasi) ..."
RESPONSE=$(curl -s -w $'\n%{http_code}' -X POST "${HASURA_URL}/v1/metadata" \
  -H "Content-Type: application/json" \
  -H "x-hasura-admin-secret: ${ADMIN_SECRET}" \
  --data-binary @"${SCRIPT_DIR}/metadata.json")

BODY="$(printf '%s' "${RESPONSE}" | sed '$d')"
STATUS="$(printf '%s' "${RESPONSE}" | tail -n1)"

echo "    HTTP ${STATUS}"
echo "    ${BODY}"

if [ "${STATUS}" = "200" ]; then
  echo "==> Berhasil! API GraphQL siap di ${HASURA_URL}/v1/graphql"
else
  echo "==> Gagal menerapkan metadata. Periksa pesan di atas." >&2
  exit 1
fi
