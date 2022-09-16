## 2.0.8 (2022-09-16)

### Bug Fixes

* Added empty_data on non-nullable string fields, set nodeName on AddNodeType ([bb027df](https://github.com/roadiz/rozier/commit/bb027dfe934a3ad3711eb17ccbae34aaa07fab4a))

## 2.0.7 (2022-09-01)

### Bug Fixes

* Trans-typing SHOULD be executed in one single SQL transaction ([9901f6b](https://github.com/roadiz/rozier/commit/9901f6b38a470c574ff0c6efd7dfca0899e95e51))

## 2.0.6 (2022-09-01)

### Bug Fixes

* Deletions buttons must be hidden for locked tags and folders ([5702bd1](https://github.com/roadiz/rozier/commit/5702bd1fac93cc119938f1085d7e4c16facef60c))

## 2.0.5 (2022-09-01)

* Translations updates

### Bug Fixes

* Fixed cssAction queries for tags and folders ([49679bf](https://github.com/roadiz/rozier/commit/49679bf70fbc8514eaad6a21ce6ff098da602bd7))

## 2.0.4 (2022-09-01)

### Features

* Added Folder `locked` and `color` form type fields and tree layout changes ([27beea1](https://github.com/roadiz/rozier/commit/27beea19d79eeaa2383dd12ca27651f806049352))
* New `ConfigurableExplorerItem` to refactor entity explorer with custom doctrine entities ([64ef927](https://github.com/roadiz/rozier/commit/64ef927dbcdbfbf7fa331fd2889621358aa19f50))

## 2.0.3 (2022-07-05)

### Bug Fixes

* Allow dev-develop versions for Roadiz bundles ([0badd5e](https://github.com/roadiz/rozier/commit/0badd5ef502aaa20ecdc88227be3a50a95571ad1))

## 2.0.2 (2022-07-01)

### Bug Fixes

* Misuse of InputBag filter args ([73469a1](https://github.com/roadiz/rozier/commit/73469a1290d97f7791ecad3d16b2b0faf6156d19))

## 2.0.1 (2022-07-01)

### Bug Fixes

* InputBag query all and filter on array ([e7a3ece](https://github.com/roadiz/rozier/commit/e7a3ece33db836b630c8a1bfbd517e57cf3e4c55))

## 2.0.0 (2022-06-30)

### ⚠ BREAKING CHANGES

* Theme requires Roadiz v2
* Changed `Rozier` twig namespace to `RoadizRozier`

### Features

* Added post flush event dispatches in AbstractAdminController ([97934f7](https://github.com/roadiz/rozier/commit/97934f73b5f8fb47ad8dc88f5ec7e3e1192756a1))
* Allow multiple events to be dispatched from AbstractAdminController ([6fa425e](https://github.com/roadiz/rozier/commit/6fa425e2bb2c25928b4f30c3534af1be314f9b20))
* Prefix all abstractAdminController template folder ([516e4db](https://github.com/roadiz/rozier/commit/516e4db56631e616c0b74bfe48e031695adb6815))
* Prefix all form themes templates ([16b8960](https://github.com/roadiz/rozier/commit/16b89602b639831bfc00a4e0246d38593172a9da))
* Prefix all templates to be overrideable ([c6fcfeb](https://github.com/roadiz/rozier/commit/c6fcfeb0b640d39ad82d7ff8f92bf5ad1d160b57))
* Remove dead code for Roadiz v2: routings, overriden classes and Pimple services ([d23eb52](https://github.com/roadiz/rozier/commit/d23eb527300643ead9c7d75e118733f0512e2f99))
* Template namespace ([0372aa9](https://github.com/roadiz/rozier/commit/0372aa97a8d736408e0f5b27f9f65f23f2a9e59b))
* Use PersistableInterface instead of AbstractEntity ([568f387](https://github.com/roadiz/rozier/commit/568f3874ea18bdd62a14cb38546618fd8b787666))

### Bug Fixes

* Check if search result is NodesSources ([99e1fe2](https://github.com/roadiz/rozier/commit/99e1fe2161e97a5c8c7822c4d7cea8554a21fc6c))
* Export document with their folder to avoid overriding same filename documents ([97ce14c](https://github.com/roadiz/rozier/commit/97ce14c7b699323129b95acb6c4fcc27c11446f6))
* Missing template namespace ([ba27c5f](https://github.com/roadiz/rozier/commit/ba27c5f2321514612fae972e414003927c5ae5fe))
* Missing translations ([d53d60b](https://github.com/roadiz/rozier/commit/d53d60b6f10e0d386bf24d0be14f525f507c959d))
* NodeSourceJoinType MUST always be multiple as data is submitted as array ([bc5282b](https://github.com/roadiz/rozier/commit/bc5282bb6f78fc6a71116b6038e8f010f78fd4c2))
* Use Request Query all method instead of get for arrays ([2414911](https://github.com/roadiz/rozier/commit/24149116d6fdfecf97046b76a9767d34d499d14a))

## 1.7.16 (2022-06-22)

### Bug Fixes

* Display always all translations for backend users, not only available ones ([fd4f44d](https://github.com/roadiz/rozier/commit/fd4f44d6c830887d31233aee5bbacb532cf2ceec))

## 1.7.14 (2022-06-02)

### Features

* Allow multiple email comma-separated in CustomFormType ([a98fa8e](https://github.com/roadiz/rozier/commit/a98fa8ee6b7d314175aa04b673371ccf79734bcb))

## 1.7.13 (2022-06-02)

### Bug Fixes

* Fixed input[type=datetime-local] style ([b04d426](https://github.com/roadiz/rozier/commit/b04d4269cf4f939da4440e0142ce7cadc054ac59))

## 1.7.12 (2022-05-25)

### Bug Fixes

* Non required CustomForm closeDate form ([5a58ee8](https://github.com/roadiz/rozier/commit/5a58ee869c1ad870cbe1befa3c35df86e3b81a8f))

## 1.7.11 (2022-04-12)

### Bug Fixes

* Use :not(:placeholder-shown):invalid instead of :invalid ([7f8bace](https://github.com/roadiz/rozier/commit/7f8bacec4064a5c7f2cd5b66c1f9b79a7841d389))

## 1.7.10 (2022-04-07)

### Bug Fixes

* Fixed nodetree style when nodes has tags ([badddb1](https://github.com/roadiz/rozier/commit/badddb1476a47253c8bd6c5e79260ae63ab9e4c4))

## 1.7.9 (2022-03-29)

### Features

* Styled invalid state for inputs ([956d12a](https://github.com/roadiz/rozier/commit/956d12a32f95aef4afd3125d79473f5ee57b9cdb))

## 1.7.8 (2022-03-29)

### Bug Fixes

* Remove user group form was not handled ([efc55aa](https://github.com/roadiz/rozier/commit/efc55aa4725def7a1c7ae377bfbd8936f6c9a1bb))

## 1.7.7 (2022-03-24)

### Features

* Dispatch UserJoinedGroupEvent and UserLeavedGroupEvent ([e895e3c](https://github.com/roadiz/rozier/commit/e895e3cc827f46704b5e0c420d9c8d1706484510))

