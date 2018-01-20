exports.config = {
    wifi: {
        name: 'WIFI_ESSID',
        options: { password : "WIFI_PASSWORD" }
    },
    plantEndpoint: {
        host: 'example.com',
        port: 80,
        protocol: 'http:', //http or https
        path: '/path/to/application',
        method: 'POST',
        headers: {
            "Content-Type":"application/x-www-form-urlencoded"
        },
        secret_token: 'YOUR_SUPER_SECRET_TOKEN',
    }
}
