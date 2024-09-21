Beaver
======

This repository contains an application with a curated set of commands for Castor CLI.
You can directly use the `castor.php` file or one of the PHAR/binary files depending on your needs.

It is designed for web application based on Symfony and running on a Docker environment.

Available commands
------------------

Docker commands:

- `beaver build`: build your Docker images
- `beaver start`: start your Docker containers
- `beaver stop`: stop your Docker containers
- `beaver restart`: restart your Docker containers
- `beaver destroy`: clean your Docker infrastructure

PHP/Symfony commands:
- `beaver php`: run a PHP command from the Docker Container
- `beaver console`: run a Symfony Console command from the Docker Container
- `beaver consume`: run a Messenger Consumer from the Docker Container
- `beaver frontend`: compile the frontend
- `beaver update`: **This command will change in a near future. Do not use.**

CI/CD commands:

- `beaver check-licenses`: check the licenses of your dependencies
- `beaver cs`: check the coding standards
- `beaver deptrac`: run Deptrac to analyze dependencies
- `beaver infect`: run mutation testing
- `beaver rector`: run Rector to upgrade code
- `beaver stan`: run PHPStan
- `beaver test`: run PHPUnit tests
- `beaver validate`: validate Composer configuration

Future commands
---------------

- `beaver deploy`: deploy your application
- `beaver release`: release a new version of your application
- `beaver rollback`: rollback to a previous version of your application
- `beaver update`: existing command will be refactored
- `beaver upgrade`: upgrade the Symfony version
- `beaver lint`: run linters
- `beaver security`: run security checks
