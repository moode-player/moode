#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
#

# Template
OPENSSL_CFG_FILE=/tmp/moode-selfsigned.conf
cat >> $OPENSSL_CFG_FILE <<EOF
[ req ]
default_bits            = 2048
encrypt_key             = no
default_md              = sha256
string_mask             = nombstr
prompt                  = no
distinguished_name      = req_dn
req_extensions          = req_ext

[ req_dn ]
commonName              = $HOSTNAME.local

[ req_ext ]
basicConstraints        = critical, CA:FALSE
keyUsage                = digitalSignature, keyEncipherment, nonRepudiation
extendedKeyUsage        = clientAuth, serverAuth
subjectAltName          = @req_sans

[ req_sans ]
DNS.1                   = $HOSTNAME.local
DNS.2                   = $HOSTNAME
IP.1                    = 172.24.1.1
EOF

# Create cert
SSL_CSR_FILE=/tmp/moode.csr
SSL_CRT_FILE=/etc/ssl/certs/moode.crt
SSL_KEY_FILE=/etc/ssl/private/moode.key
openssl req -new -config $OPENSSL_CFG_FILE -out $SSL_CSR_FILE -keyout $SSL_KEY_FILE
openssl req -x509 -days 3650 -config $OPENSSL_CFG_FILE -in $SSL_CSR_FILE -key $SSL_KEY_FILE -out $SSL_CRT_FILE -extensions req_ext

# TEST: Add to chromium-browser trust store
#sudo apt -y install libnss3-tools
#CERT_NICKNAME=moOde self-signed cert
#certutil -d sql:$HOME/.pki/nssdb -A -t "P,," -n $CERT_NICKNAME -i $SSL_CRT_FILE

# TEST: Add to RaspiOS/Debian trust store (needed?)
#sudo cp $SSL_CRT_FILE /usr/local/share/ca-certificates/
#sudo update-ca-certificates
