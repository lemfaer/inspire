# inspire
Code examples for quick quick access

## Plugins
1. [Quill, WYSIWYG editor](https://github.com/lemfaer/symfony-blog/blob/master/templates/article/form.html.twig)
2. recaptcha: [form](https://github.com/lemfaer/symfony-blog/blob/master/templates/user/registration.html.twig), [verify](https://github.com/lemfaer/symfony-blog/blob/master/src/Controller/SecurityController.php)

## HTTP Headers
#### Content-Security-Policy
1. Symfony 4: [config](https://github.com/lemfaer/symfony-blog/blob/master/config/services.php), [listener](https://github.com/lemfaer/symfony-blog/blob/master/src/Listener/ResponseListener.php)

## Server
1. nginx: [basic](https://github.com/lemfaer/symfony-blog/blob/master/nginx.conf), [https](https://github.com/lemfaer/inspire/blob/master/https-nginx.conf), [generator](https://github.com/digitalocean/nginxconfig.io)

## Routers
1. [Symfony 4 YAML](https://github.com/lemfaer/symfony-blog/blob/master/config/routes.yaml)

## Controllers
1. [Symfony 4](https://github.com/lemfaer/symfony-blog/blob/master/src/Controller/ArticleController.php)

## Database
1. [Doctrine Mappings YAML Article](https://github.com/lemfaer/symfony-blog/blob/master/config/mappings/Article.orm.yml)
2. [Doctrine Mappings YAML Tag](https://github.com/lemfaer/symfony-blog/blob/master/config/mappings/Tag.orm.yml)
3. [Doctrine Query Language](https://github.com/lemfaer/symfony-blog/blob/master/src/Repository/ArticleRepository.php)

## Views
1. [Twig Base](https://github.com/lemfaer/symfony-blog/blob/master/templates/base.html.twig)
2. [Twig List](https://github.com/lemfaer/symfony-blog/blob/master/templates/list.html.twig)
3. [Twig Form](https://github.com/lemfaer/symfony-blog/blob/master/templates/article/form.html.twig)

## Other
1. [Tags separated by comma](https://github.com/lemfaer/symfony-blog/blob/master/src/Repository/TagRepository.php)
2. [XPath img, p](https://github.com/lemfaer/symfony-blog/blob/master/src/Repository/ArticleRepository.php)
3. [Partial function](https://github.com/lemfaer/inspire/blob/master/function-partial.php)
4. [JSON RPC](https://github.com/lemfaer/inspire/blob/master/json-rpc.php)
5. [Custom SMTP](https://github.com/lemfaer/inspire/blob/master/function-putsmtp.php)
