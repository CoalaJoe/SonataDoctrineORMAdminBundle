<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\DoctrineORMAdminBundle\Tests\Filter;

use Sonata\DoctrineORMAdminBundle\Datagrid\ProxyQuery;
use Sonata\DoctrineORMAdminBundle\Filter\CallbackFilter;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class CallbackFilterTest extends FilterTestCase
{
    use ExpectDeprecationTrait;

    public function testRenderSettings(): void
    {
        $filter = new CallbackFilter();
        $filter->initialize('field_name', ['field_options' => ['class' => 'FooBar']]);
        $options = $filter->getRenderSettings()[1];

        $this->assertSame(HiddenType::class, $options['operator_type']);
        $this->assertSame([], $options['operator_options']);
    }

    public function testFilterClosure(): void
    {
        $proxyQuery = new ProxyQuery($this->createQueryBuilderStub());

        $filter = new CallbackFilter();
        $filter->initialize('field_name', [
            'callback' => static function (ProxyQuery $query, string $alias, string $field, array $data): bool {
                $query->getQueryBuilder()->andWhere(sprintf('CUSTOM QUERY %s.%s', $alias, $field));
                $query->getQueryBuilder()->setParameter('value', $data['value']);

                return true;
            },
        ]);

        $filter->filter($proxyQuery, 'alias', 'field', ['value' => 'myValue']);

        $this->assertSameQuery(['WHERE CUSTOM QUERY alias.field'], $proxyQuery);
        $this->assertSameQueryParameters(['value' => 'myValue'], $proxyQuery);
        $this->assertTrue($filter->isActive());
    }

    public function testFilterMethod(): void
    {
        $proxyQuery = new ProxyQuery($this->createQueryBuilderStub());

        $filter = new CallbackFilter();
        $filter->initialize('field_name', [
            'callback' => [$this, 'customCallback'],
        ]);

        $filter->filter($proxyQuery, 'alias', 'field', ['value' => 'myValue']);

        $this->assertSameQuery(['WHERE CUSTOM QUERY alias.field'], $proxyQuery);
        $this->assertSameQueryParameters(['value' => 'myValue'], $proxyQuery);
        $this->assertTrue($filter->isActive());
    }

    public function customCallback(ProxyQuery $query, string $alias, string $field, array $data): bool
    {
        $query->getQueryBuilder()->andWhere(sprintf('CUSTOM QUERY %s.%s', $alias, $field));
        $query->getQueryBuilder()->setParameter('value', $data['value']);

        return true;
    }

    public function testFilterException(): void
    {
        $proxyQuery = new ProxyQuery($this->createQueryBuilderStub());

        $filter = new CallbackFilter();
        $filter->initialize('field_name', []);

        $this->expectException(\RuntimeException::class);
        $filter->filter($proxyQuery, 'alias', 'field', ['value' => 'myValue']);
    }

    public function testApplyMethod(): void
    {
        $proxyQuery = new ProxyQuery($this->createQueryBuilderStub());

        $filter = new CallbackFilter();
        $filter->initialize('field_name_test', [
            'callback' => static function (ProxyQuery $query, string $alias, string $field, array $data): bool {
                $query->getQueryBuilder()->andWhere(sprintf('CUSTOM QUERY %s.%s', $alias, $field));
                $query->getQueryBuilder()->setParameter('value', $data['value']);

                return true;
            },
            'field_name' => 'field_name_test',
        ]);

        $filter->apply($proxyQuery, ['value' => 'myValue']);

        $this->assertSameQuery(['WHERE CUSTOM QUERY o.field_name_test'], $proxyQuery);
        $this->assertSameQueryParameters(['value' => 'myValue'], $proxyQuery);
        $this->assertTrue($filter->isActive());
    }

    /**
     * NEXT_MAJOR: Remove this method.
     *
     * @group legacy
     */
    public function testWrongCallbackReturnType(): void
    {
        $proxyQuery = new ProxyQuery($this->createQueryBuilderStub());

        $filter = new CallbackFilter();
        $filter->initialize('field_name', [
            'callback' => static function (ProxyQuery $query, string $alias, string $field, array $data): int {
                $query->getQueryBuilder()->andWhere(sprintf('CUSTOM QUERY %s.%s', $alias, $field));
                $query->getQueryBuilder()->setParameter('value', $data['value']);

                return 1;
            },
        ]);

        $this->expectDeprecation(
            'Using another return type than boolean for the callback option is deprecated'
            .' since sonata-project/doctrine-orm-admin-bundle 3.25 and will throw an exception in version 4.0.'
        );
        $filter->filter($proxyQuery, 'alias', 'field', ['value' => 'myValue']);

        $this->assertSameQuery(['WHERE CUSTOM QUERY alias.field'], $proxyQuery);
        $this->assertSameQueryParameters(['value' => 'myValue'], $proxyQuery);
    }
}
