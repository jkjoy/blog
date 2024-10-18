部署方式
```yaml
services:
  moments:
    image: jkjoy/moments:latest
    environment:
      JWT_KEY: "BbYS93dHHfIC1cQR8rI6"
      WEBHOOK_URL: "https://open.feishu.cn/open-apis/bot/v2/hook/*" #飞书webhook 
      SITE_URL: "https://www.moments.cn" #访问地址
      QQ_WEBHOOK_URL: "https://http.asbid.cn/send_private_msg" #QQ机器人的API
      QQ_USER_ID: "123456" #接收消息的QQ号码
    ports:
      - "3000:3000"
    volumes:
      - ./data:/app/data
      - /etc/localtime:/etc/localtime:ro
      - /etc/timezone:/etc/timezone:ro
```
 
