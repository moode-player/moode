#!/bin/bash
#
# moOde audio player (C) 2014 Tim Curtis
# http://moodeaudio.org
#
# This Program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 3, or (at your option)
# any later version.
#
# This Program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
# NOTE: In the Subject Alternative Name section (req-sans) the 172.24.1.1
# address is for Access Point mode.
#

# Template
OPENSSL_CFG_FILE=/tmp/nginx-selfsigned.conf
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
SSL_CSR_FILE=/tmp/nginx-selfsigned.csr
SSL_CRT_FILE=/etc/ssl/certs/nginx-selfsigned.crt
SSL_KEY_FILE=/etc/ssl/private/nginx-selfsigned.key
openssl req -new -config $OPENSSL_CFG_FILE -out $SSL_CSR_FILE -keyout $SSL_KEY_FILE
openssl req -x509 -days 3650 -config $OPENSSL_CFG_FILE -in $SSL_CSR_FILE -key $SSL_KEY_FILE -out $SSL_CRT_FILE -extensions req_ext

# TEST: Add to chromium-browser trust store
#sudo apt -y install libnss3-tools
#CERT_NICKNAME=NGINX Self-signed Cert
#certutil -d sql:$HOME/.pki/nssdb -A -t "P,," -n $CERT_NICKNAME -i $SSL_CRT_FILE

# TEST: Add to RaspiOS/Debian trust store (needed?)
#sudo cp $SSL_CRT_FILE /usr/local/share/ca-certificates/
#sudo update-ca-certificates
