# FTNS - server side system for FTNW & ifmail & binkd

Серверная часть фидоноды ftns/ftnw, её задачи и функции таковы:
- Прием сообщений от ifmail/ifnews и помещение их в спулы.
- Тоссинг rfc-0822/rfc-1036 спуленых сообщений и раскидывание их классическим поинтам и в базу для ббс-поинтов.
- Сканирование эхо- и нетмыла в базе и помещение в спулы тоссера.
- Роутинг мыла (пока заплатка, в разработке).
- Подписка (отписка) на эхи у аплинков.
- Areafix - общий для классических и ббс-поинтов.
- Синхронизация поинтов между базой ftnw и конфигом ftns.
- Синхронизация эх узла между конфигом ftns и базой ftnw.
- Синхронизация подписок поинтов между конфигом ftns и базой ftnw.

Замена ifmail на встроенный тоссер FTS-0001 пакетов планируется, но позже.

## Установка

Вся установка делается с помощью <code>composer</code>, сначала создаем директорию там, где на нужно:
```
mkdir ftns && cd ftns
```
Загружаем файлы:
```
composer require igorgoroun/ftns
```
Выполняем пост-инсталляционный скрипт:
```
cd vendor/igorgoroun/ftns/ && composer run-script post-install-cmd
```

## Настройка

Теперь возвращаемся в корень, туда скопировалась директория <code>etc/</code>, основной файл <code>ftns</code> и маленький bash-скрипт <code>ftns-toss</code>.

### etc/ftns.yml
Главный конфиг-файл, правим согласно нашим потребностям.

### ftns-toss
Shell-скрипт проверяющий наличие сообщений в спуле нетмейла и эхомейла и запускающий соответсвующий тоссер.
Путь к спул-директории нетмыла:
```
NM_DIR="/var/spool/ftn/netmailspool/"
```
Путь к спул-директории эхомыла:
```
EM_DIR="/var/spool/ftn/echospool/"
```
Полный абсолютный путь с файлу <code>ftns</code>:
```
FTNS_PATH=“/opt/ftns/ftns"
```

## Команды ftns
<code>./ftns list</code> - список доступных команд.
Для каждой команды можно получить подсказку, например:
```
./ftns help echomail:post
```
#### Echomail
<code>./ftns echomail:spool</code> - принимает на STDIN сообщение от ifnews и складывает в echomail_spool.
<code>./ftns echomail:toss</code> - тоссит сообщения в echomail_spool и раскладыает их поинтам.
<code>./ftns echomail:scan</code> - смотрит новые сообщения в БД и складывает их для тоссера в echomail_spool.
<code>./ftns echomail:subscribe Point_IFAddr Area1 Area2 …</code> - Подписывает поинта на эхи. Point_IFAddr в формате *p34.f4.n466.z2.fidonet.org*.
<code>./ftns echomail:newarea Uplink_IFAddr Area1 Area2 …</code> - Подписаться у аплинка на эху и внести её в конфиг. Uplink_IFAddr в формате *f55.n466.z2.fidonet.org*.
<code>./ftns echomail:post -s “Subject” -m “Message” -t “Tearline” -o “Origin” ECHOAREA</code> - Отправить сообщение в эху

#### Netmail
<code>./ftns netmail:spool -f From_RFC -t To_RFC </code> - принимает от ifmail на STDIN сообщения и складывает их в netmail_spool
<code>./ftns netmail:toss</code> - тоссит сообщения в netmail_spool и раскладыает их поинтам.
<code>./ftns netmail:scan</code> - смотрит новые нетмейл-сообщения в БД и складывает их для тоссера в netmail_spool.

#### Sync
<code>./ftns sync:points</code> - Синхронизирует поинтов в ftns с конфиг-файлом ftns.
<code>./ftns sync:subscr</code> - Синхронизирует подписки поинтов между ftns и ftnw.
<code>./ftns sync:areas</code> - Синхронизирует список доступных эх между ftns и ftnw.

## Настройки ifmail
Покажу только нюансы связанные с ftns.
#### Areas
У меня только одна запись в файле, этого достаточно:
```
* * world
```
#### config
Важные две настройки - отвязка от классической связки ifmail+sendmail+innd:
```
sendmail /var/www/fidonews-server/ftns netmail:spool --from=$F --to=$T
```
```
rnews /var/www/fidonews-server/ftns echomail:spool
```
Остальные настройки - на ваше усмотрение, там фактически ничего больше нет важного, кроме данных сисопа.

## Настройки binkd
Ftns генерирует файл *points.inc* с паролями поинтов в директории конфигов **binkd**, у меня это <code>/etc/binkd</code>, так что этот файл должен быть создан и доступен для записи.
В конфиг binkd должна быть добавлена команда для подключения файла:
```
include /etc/binkd/points.inc
```

## crontab
Как и что запускать - дело ваше, у меня все скрипты работают по крону, примерно вот так:
```
*/1 * * * * ftn /usr/lib/ifmail/ifpack 2>&1
*/1 * * * * ftn /usr/lib/ifmail/ifunpack 2>&1
*/1 * * * * ftn /usr/bin/php /var/www/fidonews-server/ftns netmail:scan 2>&1
*/3 * * * * ftn /usr/bin/php /var/www/fidonews-server/ftns echomail:scan 2>&1
*/2 * * * * ftn /var/www/fidonews-server/ftns-toss 2>&1
*/10 * * * * ftn /usr/bin/php /var/www/fidonews-server/ftns sync:subscr 2>&1
*/10 * * * * ftn /usr/bin/php /var/www/fidonews-server/ftns sync:points 2>&1
0 0 * * * ftn /usr/bin/php /var/www/fidonews-server/ftns echomail:post r46.alive -m "Ping" -s "Alive" 2>&1
```
