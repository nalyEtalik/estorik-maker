<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twc\MakerBundle\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ManagerRegistry as LegacyManagerRegistry;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Bundle\MakerBundle\Util\UseStatementGenerator;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping;
/**
 * @internal
 */
final class EntityClassGenerator
{
    private $generator;
    private $doctrineHelper;
    private $managerRegistryClassName = LegacyManagerRegistry::class;

    public function __construct(Generator $generator, DoctrineHelper $doctrineHelper)
    {
        $this->generator = $generator;
        $this->doctrineHelper = $doctrineHelper;
    }

    public function generateEntityClass(ClassNameDetails $entityClassDetails, ClassNameDetails $repoClassDetails, bool $apiResource, bool $withPasswordUpgrade = false, bool $generateRepositoryClass = true, bool $broadcast = false): string
    {

        $tableName = $this->doctrineHelper->getPotentialTableName($entityClassDetails->getFullName());

        $useStatements = new UseStatementGenerator([
            $repoClassDetails->getFullName(),
            [Mapping::class => 'ORM'],
        ]);

        if ($apiResource) {
            // @legacy Drop annotation class when annotations are no longer supported.
            $useStatements->addUseStatement(class_exists(ApiResource::class) ? ApiResource::class : \ApiPlatform\Core\Annotation\ApiResource::class);
        }



        $entityPath = $this->generator->generateClass(
            $entityClassDetails->getFullName(),
            'doctrine/Entity.tpl.php',
            [
                'use_statements' => $useStatements,
                'repository_full_class_name' => $repoClassDetails->getFullName(),
                'repository_class_name' => $repoClassDetails->getShortName(),
                'api_resource' => $apiResource,
                'broadcast' => $broadcast,
                'should_escape_table_name' => $this->doctrineHelper->isKeyword($tableName),
                'table_name' => $tableName,
                'doctrine_use_attributes' => $this->doctrineHelper->isDoctrineSupportingAttributes() && $this->doctrineHelper->doesClassUsesAttributes($entityClassDetails->getFullName()),
            ]
        );

        if ($generateRepositoryClass) {
            $this->generateRepositoryClass(
                $repoClassDetails->getFullName(),
                $entityClassDetails->getFullName(),
                $withPasswordUpgrade,
                true
            );
        }

        return $entityPath;
    }

    public function generateRepositoryClass(string $repositoryClass, string $entityClass, bool $withPasswordUpgrade, bool $includeExampleComments = true)
    {

        $shortEntityClass = Str::getShortClassName($entityClass);
        $entityAlias = strtolower($shortEntityClass[0]);

        $passwordUserInterfaceName = UserInterface::class;

        if (interface_exists(PasswordAuthenticatedUserInterface::class)) {
            $passwordUserInterfaceName = PasswordAuthenticatedUserInterface::class;
        }

        $interfaceClassNameDetails = new ClassNameDetails($passwordUserInterfaceName, 'Symfony\Component\Security\Core\User');

        $useStatements = new UseStatementGenerator([
            $entityClass,
            ManagerRegistry::class,
            ServiceEntityRepository::class,
        ]);

        if ($withPasswordUpgrade) {
            $useStatements->addUseStatement([
                $interfaceClassNameDetails->getFullName(),
                PasswordUpgraderInterface::class,
                UnsupportedUserException::class,
            ]);
        }

        $this->generator->generateClass(
            $repositoryClass,
            'doctrine/Repository.tpl.php',
            [
                'use_statements' => $useStatements,
                'entity_full_class_name' => $entityClass,
                'entity_class_name' => $shortEntityClass,
                'entity_alias' => $entityAlias,
                'with_password_upgrade' => $withPasswordUpgrade,
                'doctrine_registry_class' => $this->managerRegistryClassName,
                'password_upgrade_user_interface' => $interfaceClassNameDetails,
                'include_example_comments' => $includeExampleComments,
            ]
        );
    }

    /**
     * Called by a compiler pass to inject the non-legacy value if available.
     */
    public function setMangerRegistryClassName(string $managerRegistryClassName)
    {
        $this->managerRegistryClassName = $managerRegistryClassName;
    }
}
