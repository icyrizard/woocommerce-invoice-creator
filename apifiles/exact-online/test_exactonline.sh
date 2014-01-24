APPID="07cae1bf-27a1-4c6a-a4a3-572ae7866bc6"
APIKEY="B83AsnnwYu2g"
USER="info@sponiza.nl"
PASSWORD=Nhu22VaQ
echo $USER:$PASSWORD
AUTH=`echo "$USER:$PASSWORD"| base64`
echo 1.AUTH: $AUTH
echo 2.AUTH: `echo $AUTH | base64 --decode`
division=545462
urlext="api/v1/$division/crm/Accounts"
urlext="545462/crm/BankAccounts"
url="https://start.exactonline.nl/api/v1/$urlext"
echo $url

curl -3 -v -H "Authorization: Basic $AUTH" \
        -H "X-ExactOnline-ApplicationKey: $APPID" \
        -H "Accept: application/json" $url

#curl -v -H "Authorization: Basic $AUTH" \
#        -H "X-ExactOnline-ApplicationKey: $APPID" \
#        -H "Accept: application/json" $url

#curl -v -H "Content-Type: application/x-www-form-urlencoded"\
#        -X POST -d "_UserName_=info@sponiza.nl&_Password_=Nhu22VaQ" \
#        $url

