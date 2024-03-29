## 2.2.0

#### New features

* Add `ChainingClassResolver::setClassTargetResolvers()` to allow for multiple chain resolvers to replace target classes in source files.

### 2.1.1

#### Bugfixes

* Adding in constants was failing when a `{` was present in the code source

## 2.1.0

#### New features

* `ChainedClassMeta::get()` now preloads the class so that the meta is consistent

### 2.0.2

* Bugfix: Broken chained code when using `__DIR__` constant

### 2.0.1

#### Improvements

* Upgrade `exteon/mapping-class-loader`

# 2.0.0

#### Changes

* Removed `ModuleRegistry`; modules are now sent directly in the constructor
* PHP 8 is required
* Method and properties visibility is now set conservatively (private instead 
  of protected).

## 1.1.0

#### New features

* Added `ChainedClassMeta::hasChainTrait()`

#### Bugfixes

* `ChainedClassMeta::getChainTraits()` was not traversing chained ancestor 
  traits when it was called on a trait

### 1.0.2

* Upgraded `exteon/mapping-class-loader`