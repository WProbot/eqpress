#!/bin/bash
export PATH=/bin:/usr/bin:/sbin:/usr/sbin:/usr/local/bin:/usr/local/sbin
export HOME=/root

basedir=/var/www/easypress.ca
ansible=${basedir}/ansible
prov=${basedir}/provision
pending=${prov}/pending
processing=${prov}/processing
processed=${prov}/processed

sites=`find ${pending}/* -type d -exec basename {} \; 2> /dev/null`
if [ -n "$sites" ]; then
    #
    # wait a second just in case provision process is still mid-write
    sleep 1
    for site in ${sites}; do
        mv ${pending}/${site} ${processing} || ( echo Failed to move ${site}; exit 1 )
    done

    cd ${ansible}
    source ./hacking/env-setup -q

    for site in ${sites}; do
        cd ${processing}/${site}
        ansible-playbook ep-provision.yml
        #
        # delete duplicate directories
        if [ -d ${processed}/${site} ]; then
            echo ${site} already existed in ${processed}
            rm -rf ${processed}/${site}
        fi
        #
        # rename duplicate site archives
        if [ -f ${processed}/${site}.tar.gz.gpg ]; then
            echo ${site} encrypted copy of ${site} already existed in ${processed}
            mv ${processed}/${site}.tar.gz.gpg ${processed}/${site}.tar.gz.gpg-$(date +%Y%m%d%H%M%S)
        fi
        mv ${processing}/${site} ${processed}
        cd ${processed}
        tar cfz ${site}.tar.gz ./${site}
        rm -rf ${site}
        gpg -e -r {{ auto_provision_gpg_id }} --trust-model always ${site}.tar.gz
        rm -f ${site}.tar.gz
    done

    exit 1
else
    exit 0
fi
