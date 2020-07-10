FROM php:7.3-cli

ARG USERNAME=vscode
ARG USER_UID=1000
ARG USER_GID=$USER_UID

ENV BOXVERSION 3.8.4
ENV BOXURL https://github.com/humbug/box/releases/download/${BOXVERSION}/box.phar
ENV PLEASINGVERSION 1.1.2
ENV PLEASINGURL https://github.com/jonesiscoding/pleasing-tools/releases/download/v${PLEASINGVERSION}/pleasing.phar

RUN \
  apt-get update \
  && export DEBIAN_FRONTEND=noninteractive \
  && apt-get -y install --no-install-recommends apt-utils dialog 2>&1 \
  # install git iproute2, procps, lsb-release (useful for CLI installs)
  && apt-get -y install git openssh-client less iproute2 procps iproute2 lsb-release \
  # so we can compile sass to css
  sassc \
  nodejs \
  npm \
  node-babel-cli \
  node-babel-preset-env \
  #
  # Install Composer
  #
  && curl -o /tmp/composer-setup.php https://getcomposer.org/installer \
  && curl -o /tmp/composer-setup.sig https://composer.github.io/installer.sig \
  && php -r "if (hash('SHA384', file_get_contents('/tmp/composer-setup.php')) !== trim(file_get_contents('/tmp/composer-setup.sig'))) { unlink('/tmp/composer-setup.php'); echo 'Invalid installer' . PHP_EOL; exit(1); }" \
  && php /tmp/composer-setup.php --no-ansi --install-dir=/usr/local/bin --filename=composer --snapshot \
  && rm -f /tmp/composer-setup.* \
  #
  # Install Box
  #
  && BOX_FILE=$(mktemp) \
  && curl -o "$BOX_FILE" -SL "${BOXURL}" \
  && mkdir -p /usr/local/bin/ \
  && mv ${BOX_FILE} /usr/local/bin/box; chmod 755 /usr/local/bin/box \
  #
  # Install Pleasing
  #
  && PLEASING_FILE=$(mktemp) \
  && curl -o "$PLEASING_FILE" -SL "${PLEASINGURL}" \
  && mv ${PLEASING_FILE} /usr/local/bin/pleasing; chmod 755 /usr/local/bin/pleasing \
  && chmod 755 /usr/local/bin/pleasing \
  #
  # Create a non-root user to use if preferred - see https://aka.ms/vscode-remote/containers/non-root-user.
  && groupadd --gid $USER_GID $USERNAME \
  && useradd -s /bin/bash --uid $USER_UID --gid $USER_GID -m $USERNAME \
  # [Optional] Add sudo support for the non-root user
  && apt-get install -y sudo \
  && echo $USERNAME ALL=\(root\) NOPASSWD:ALL > /etc/sudoers.d/$USERNAME\
  && chmod 0440 /etc/sudoers.d/$USERNAME \
  #
  # Clean up
  && apt-get autoremove -y \
  && apt-get clean -y \
  && rm -rf /var/lib/apt/lists/*