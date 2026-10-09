# Évolutions `letots/workflow-extension-bundle`

## `AbstractWorkflow.php`

- Ajouter `?EventDispatcherInterface $eventDispatcher = null` au constructeur.
- Passer `$eventDispatcher` à `parent::__construct()` à la place de `null`.
- Passer le `type` de l'attribut `#[AsWorkflow]` à `getTransitions()` pour la validation optionnelle (voir ci-dessous).

```php
public function __construct(
    ?EventDispatcherInterface $eventDispatcher = null,
) {
    // ...
    $attributeInstance = $workflowAttributes[0]->newInstance();
    $transitions = $this->getTransitions($places, $attributeInstance->type);
    // ...
    parent::__construct($definition, $markingStore, $eventDispatcher, $workflowName, null);
}
```

---

## `WorkflowExtensionCompilerPass.php`

- Ne plus appeler `$container->register($class)` à blanc (écrase l'autowiring applicatif).
- Enrichir la définition existante ou la créer si absente.
- Injecter `@event_dispatcher` sur le paramètre `$eventDispatcher`.
- Alias `workflow.{name}` → service concret (supprimer la factory `[Reference($class), '__construct']`).
- Conserver l'enregistrement dans `Registry` si présent, avec `InstanceOfSupportStrategy` quand `supportStrategy` est une class-string.

```php
if ($container->hasDefinition($class)) {
    $workflowDefinition = $container->findDefinition($class);
} else {
    $workflowDefinition = $container->register($class)->setClass($class);
}

$workflowDefinition
    ->setPublic(true)
    ->setAutowired(true)
    ->setArgument('$eventDispatcher', new Reference('event_dispatcher'));

$container->setAlias('workflow.'.$workflowName, $class)->setPublic(true);

$container->registerAliasForArgument(
    $class,
    WorkflowInterface::class,
    $workflowName,
);

if ($container->hasDefinition(Registry::class) && null !== $workflowAttr->supportStrategy) {
    $registryDefinition = $container->findDefinition(Registry::class);
    $supportStrategy = is_string($workflowAttr->supportStrategy)
        ? new Definition(InstanceOfSupportStrategy::class, [$workflowAttr->supportStrategy])
        : $workflowAttr->supportStrategy;

    $registryDefinition->addMethodCall('addWorkflow', [
        new Reference($class),
        $supportStrategy,
    ]);
}
```

Imports à ajouter si manquants : `Definition`, `InstanceOfSupportStrategy`.

---

## `getTransitions()` — validation state machine (optionnel)

Dans `AbstractWorkflow::getTransitions(array $places, string $type)` :

- Si `$type === AsWorkflow::TYPE_STATE_MACHINE` et `from` ou `to` est un tableau de plus d'un élément → `InvalidArgumentException` avec message indiquant d'utiliser plusieurs constantes `#[Transition]` partageant le même nom de transition.

```php
if ($type === AsWorkflow::TYPE_STATE_MACHINE) {
    if (is_array($from) && count($from) !== 1) {
        throw new InvalidArgumentException(sprintf(
            'State machine transition "%s" (%s) must declare a single `from` place per constant. '
            .'Use multiple #[Transition] constants sharing the same transition name instead of an array.',
            $reflectionClassConstant->getName(),
            $reflectionClassConstant->getValue(),
        ));
    }
    if (is_array($to) && count($to) !== 1) {
        throw new InvalidArgumentException(sprintf(
            'State machine transition "%s" (%s) must declare a single `to` place per constant.',
            $reflectionClassConstant->getName(),
            $reflectionClassConstant->getValue(),
        ));
    }
}

$fromPlace = is_array($from) ? $from[0] : $from;
$toPlace = is_array($to) ? $to[0] : $to;

$transitions[] = new WorkflowTransition(
    $reflectionClassConstant->getValue(),
    $fromPlace,
    $toPlace,
);
```

---

## `README.md` (optionnel)

Documenter : en `TYPE_STATE_MACHINE`, une constante `#[Transition]` = un seul arc `from → to` ; plusieurs origines = plusieurs constantes, même valeur de transition.

---

## `WorkflowInterface.php` (obligatoire — oublié en 0.1.1)

Aligner la signature avec `AbstractWorkflow::getTransitions()` :

```php
public function getTransitions(array $places, string $type): array;
```

Sans ça → fatal error au boot :
`Declaration of AbstractWorkflow::getTransitions(...) must be compatible with WorkflowInterface::getTransitions(...)`

---

## Bump version

Ex. `0.1.1` → `0.1.2` (ne pas republier 0.1.1 : signature déjà cassée sur Packagist).
