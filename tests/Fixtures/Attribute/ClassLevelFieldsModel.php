<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Fixtures\Attribute;

use DH\Auditor\Attribute\DiffLabel;
use DH\Auditor\Attribute\Ignore;
use DH\Auditor\Attribute\Mask;

/**
 * A model without a declared property per column, as an Eloquent model.
 */
#[Ignore(field: 'password')]
#[Ignore(field: 'remember_token')]
#[DiffLabel(resolver: 'App\Resolver\CategoryLabelResolver', field: 'category_id')]
#[DiffLabel(resolver: 'App\Resolver\StatusLabelResolver', field: 'status')]
#[Mask(strategy: Mask::KEYED_HASH, field: 'email')]
#[Mask(field: 'phone')]
final class ClassLevelFieldsModel {}
