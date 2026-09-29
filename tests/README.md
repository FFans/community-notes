# 测试

使用 PHP 8.3 及以上版本和 Flarum 2.x。

## 常用命令

先安装 Composer 依赖，并确保 `js/dist/forum.js` 和 `js/dist/admin.js` 已生成。
首次运行集成测试前，在扩展根目录执行：

```sh
composer test:setup
composer test
```

| 命令 | 职责 |
| --- | --- |
| `composer test:unit` | 运行 `tests/Unit`，不启动论坛或连接数据库，无须初始化 |
| `composer test:integration` | 运行 `tests/Integration`，复用初始化好的论坛和数据库 |
| `composer test:setup` | 初始化或重建独立论坛、核心与扩展表，并启用扩展 |
| `composer test:smoke` | 在另一个临时 SQLite 论坛检查启停、重启、JS/LESS 资产与 HTTP 访问，无须预先初始化 |
| `composer test` | 依次运行单元测试、集成测试和冒烟测试 |

单独运行或筛选测试：

```sh
composer test:unit -- --filter EnumsTest
composer test:integration -- --filter NoteApiTest
```

## 集成测试环境

本地默认使用 `tests/tmp/integration/database.sqlite` 和 `cn_test_` 表前缀，需要启用 `pdo_sqlite`。
`test:setup` 负责安装、迁移和扩展启用；`test:integration` 只启动已准备好的论坛，
每个测试使用独立 PHP 进程，并通过数据库事务回滚测试数据。
迁移回滚与重装测试在数据事务之外执行 DDL，重装后重新开启事务，避免 MySQL/MariaDB
隐式提交留下测试数据。写入失败测试通过数据库连接的查询钩子注入异常，保留真实事务回滚，
不依赖 SQLite 触发器或固定表前缀。
修改迁移、依赖或测试环境配置后，应重新运行 `composer test:setup`。

目录按以下优先级确定，初始化与测试必须使用相同设置：

1. `FLARUM_TEST_TMP_DIR_LOCAL`
2. `FLARUM_TEST_TMP_DIR`
3. 扩展内的 `tests/tmp/integration`

相对目录以扩展根目录为基准。沿用模板 CI 的 `tests/integration/tmp` 也受支持。
SQLite 数据库始终放在所选目录的 `database.sqlite` 中；不会读取本机论坛配置。

如需测试 MySQL、MariaDB 或 PostgreSQL，在运行 `test:setup` 前设置 `DB_DRIVER`、
`DB_HOST`、`DB_PORT`、`DB_DATABASE`、`DB_USERNAME`、`DB_PASSWORD`，必要时设置
`DB_SCHEMA` 和 `DB_PREFIX`。显式设置为空的 `DB_PREFIX` 会保留为空。
这些配置会写入隔离目录的 `config.php`，后续集成测试读取该文件。

**`test:setup` 会清空指定测试数据库中的表和视图，必须使用专用测试数据库。**
重复初始化不会批量删除已有文件或目录。冒烟测试始终使用另建的 SQLite 目录，
不会复用或重建集成测试数据库。

现有后端 CI 先执行 `composer test:setup`，再执行 `composer test`。
集成测试使用 CI 注入的数据库驱动、连接配置及表前缀，不会强制切换为 SQLite。
