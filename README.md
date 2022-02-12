# ytm-webremote

YTMDesktop PHP web remote. Deploy on local server or use from ytm.hassing.org.

## FAQ

_Q: Why not call the YTMDesktop API directly from javascript?_

Browsers like Chrome no longer allows calling HTTP and will automatically redirect to HTTPS. The YTMDesktop API is HTTP only.

_Q: I can't connect to [lan-ip]:9863?_

The API port must be forwarded on your router to allow access from the internet to use it from ytm.hassing.org. Install on a local server to use it without opening for internet trafic.

## Screenshot

![](screenshot.png)
