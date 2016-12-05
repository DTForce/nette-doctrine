[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/DTForce/nette-doctrine/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/DTForce/nette-doctrine/?branch=master)
# nette-doctrine
A lightweight Doctrine integration extension for Nette framework. This extension is a replacement of Kdyby\Doctrine,
suitable for those who want to use native Doctrine classes and don't want register entity manager in the Nette 
service container themselves. It is compatible with Kdyby\Console.

## Configuration
Add extension to Nette project like this:

```yaml
extensions:
	doctrine: DTForce\DoctrineExtension\DI\DoctrineExtension
```

Configure Doctrine access and other parameters like this:

```yaml
doctrine:
	connection:
		driver: pdo_pgsql
		host: localhost
		port: 5432
		user: username
		password: password
		dbname: database

	debug: true
	prefix: doctrine.default
	proxyDir: %tempDir%/cache/proxies
	sourceDir: %appDir%/Entity

	dbal:
		types:
			dateinterval: App\Doctrine\Postgresql\Types\DateIntervalType
		type_overrides:
			date: VasekPurchart\Doctrine\Type\DateTimeImmutable\DateTimeImmutableType
			datetime: VasekPurchart\Doctrine\Type\DateTimeImmutable\DateTimeImmutableType
			datetimetz: VasekPurchart\Doctrine\Type\DateTimeImmutable\DateTimeTzImmutableType
		schema_filter: "~^(?!nd_)~" # tables and sequences that start with nd_ are ingored by Doctrine

	functions:
		CAST: App\Doctrine\Postgresql\Functions\Cast
```	

## Tweaking
### Mapping classes
To create mapping between classes used in annotations and the actually instantiated classes create a Nette extension
implementing `IClassMappingProvider`. Method `getClassnameToClassnameMapping` is expected to return mapping using class
used for annotations as its key and class actually instantiated as the associated value.

### Adding entity source directories
To register different source directories for different extensions, let your extension implement `IEntitySourceProvider`.
Method `getEntityFolderMappings` is expected to return list of folders, where Doctrine entities can be found. Key of the
returned array is ignored.
