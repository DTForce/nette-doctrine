# nette-doctrine
Lightweight Doctrine integration extension for Nette framework.

# Configuration
Add extension to Nette project like this:
```yaml
extensions:
	doctrine: DTForce\DoctrineExtension\DI\DoctrineExtension
```
Configure Doctrine access and other parameters like this:
```yaml
doctrine:
	config:
		driver: pdo_pgsql
		host: localhost
		port: 5432
		user: username
		password: password
		dbname: database

	debug: true
	prefix: doctrine.default
	proxyDir: %tempDir%/cache/proxies
```	
