#!/bin/bash

# Usage: ./generate-site-cert.sh <site-name> <domain>
# Example: ./generate-site-cert.sh wordpress kikujo.s325.xrea.com

if [ $# -ne 2 ]; then
    echo "Usage: $0 <site-name> <domain>"
    echo "Example: $0 wordpress kikujo-wp.local"
    exit 1
fi

SITE_NAME=$1
DOMAIN=$2
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SITES_DIR="${SCRIPT_DIR}/sites"
CA_CERT="${SCRIPT_DIR}/ca/ca-cert.pem"
CA_KEY="${SCRIPT_DIR}/ca/ca-key.pem"
TEMPLATE="${SCRIPT_DIR}/cert-template.conf"

echo "Generating SSL certificate for ${DOMAIN}..."

# Create temporary config file with domain substitution
TEMP_CONF="${SITES_DIR}/${SITE_NAME}-temp.conf"
sed "s/\${DOMAIN}/${DOMAIN}/g" $TEMPLATE > $TEMP_CONF

# Generate private key
openssl genrsa -out "${SITES_DIR}/${SITE_NAME}.key" 2048

# Generate CSR
openssl req -new -key "${SITES_DIR}/${SITE_NAME}.key" \
    -out "${SITES_DIR}/${SITE_NAME}.csr" \
    -config $TEMP_CONF

# Sign certificate with CA
openssl x509 -req -in "${SITES_DIR}/${SITE_NAME}.csr" \
    -CA $CA_CERT -CAkey $CA_KEY -CAcreateserial \
    -out "${SITES_DIR}/${SITE_NAME}.crt" \
    -days 365 -extensions v3_req -extfile $TEMP_CONF

# Cleanup temporary files
rm "${SITES_DIR}/${SITE_NAME}.csr" $TEMP_CONF

echo "Certificate generated: ${SITES_DIR}/${SITE_NAME}.crt"
echo "Private key: ${SITES_DIR}/${SITE_NAME}.key"
