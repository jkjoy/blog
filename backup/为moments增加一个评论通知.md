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
 没有修改原版的数据库,使用系统变量读取,

`WEBHOOK_URL`为你使用的`webhook`地址, 可以是`飞书webhook`, 也可以是其他的. 
`SITE_URL`为你的moments的访问地址, 可以是域名,也可以是ip地址.用来拼接memo的访问地址

`QQ_WEBHOOK_URL`为你使用的QQ机器人的API地址,需要自行部署,或者使用公共服务 使用gocqhttp的API接口 

端点地址`/send_private_msg`时`QQ_USER_ID`为你接收消息的QQ号码.

 端点`/send_group_msg`时,`QQ_USER_ID`为你接收消息的QQ群号码.
