## [4.0.1]
### Changes
- updated event dispatcher

## [4.0.2]
### Fixes
- Fixed `Fail to parse address "null"` error

## [4.0.3]
### Improvements
- improved pipelines (now session sends all messages at once like it should)
- added setting to use `TLSMODE_REQUIRED_NO_VALIDATION` for self signed certificates
- fixed Time and DateTimeOffset type conversions
- added ability to select database for Neo4j V4+
- added ability to provide bookmarks for Neo4j V4+
