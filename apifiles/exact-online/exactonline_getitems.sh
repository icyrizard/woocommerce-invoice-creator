#APPID=07cae1bf-27a1-4c6a-a4a3-572ae7866bc6
division=545462
baseurl="https://start.exactonline.nl"
APPID="07cae1bf-27a1-4c6a-a4a3-572ae7866bc6"

#curl -v -H "Content-Type: application/x-www-form-urlencoded"\
#        -X POST -d "_UserName_=info@sponiza.nl&_Password_=Nhu22VaQ" \
#        $url


url="$baseurl/docs/XMLDownload.aspx?Topic=items&output=1&ApplicationKey=$APPID"
echo $url

curl -o output.html -v -H "Content-Type: application/x-www-form-urlencoded"\
        -X POST -d "_UserName_=info@sponiza.nl&_Password_=Nhu22VaQ" \
        $url

