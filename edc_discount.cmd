!! wait 1m
!! clearcookies
open https://www.one-dc.com/nl/login.html
wait page https://www.one-dc.com/nl/login.html
js document.getElementById("gebr").value = "rootdev@gmail.com";
js document.getElementById("pwd").value = "fa9]qNuuUMGB";
js document.getElementById("acc_login_left").getElementsByTagName("form")[0].submit.click();
wait page https://www.one-dc.com/nl/my_overview
download_txt https://www.one-dc.com/nl/download/discountoverview.csv?apikey=35t55w94ec2833998860r3e5626eet1c
