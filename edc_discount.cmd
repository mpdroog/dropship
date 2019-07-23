!! wait 1m
!! mail rootdev@gmail.com
!! use resource
!! onerror screenshot
open https://www.erotischegroothandel.nl/login.html
wait page
js document.getElementById("gebr").value = "rootdev@gmail.com";
js document.getElementById("pwd").value = "fa9]qNuuUMGB";
js document.getElementById("acc_login_left").getElementsByTagName("form")[0].submit.click();
wait page
download_txt https://www.erotischegroothandel.nl/download/discountoverview.csv?apikey=35t55w94ec2833998860r3e5626eet1c
