FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && \
    apt-get install -y \
        dbus \
        dbus-x11 \
        policykit-1 \
        hplip \
        hplip-gui \
        sane \
        sane-utils \
        libsane-hpaio \
        iputils-ping \
        curl \
        && \
    rm -rf /var/lib/apt/lists/*

RUN mkdir -p /run/dbus

CMD ["/bin/bash"]