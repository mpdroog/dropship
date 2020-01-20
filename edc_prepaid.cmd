!! wait 1m
!! clearcookies
!! mail rootdev@gmail.com
!! use resource
!! onerror screenshot
open https://www.erotischegroothandel.nl/login.html
wait page https://www.erotischegroothandel.nl/login.html
js document.getElementById("gebr").value = "rootdev@gmail.com";
js document.getElementById("pwd").value = "fa9]qNuuUMGB";
js document.getElementById("acc_login_left").getElementsByTagName("form")[0].submit.click();
wait page https://www.erotischegroothandel.nl/mijn_overzicht/
download_txt https://www.erotischegroothandel.nl/mijn_overzicht/ao_menu/
# done!
