## Overview

- Hàm formula chạy 1 đoạn script formula bất kỳ
- Lưu ý nguy cơ recurrsive call chính nó khi trong script cần chạy chứa hàm này

## Các hàm custom formula
- Hàm nhận đầu vào là entityType, id và formula script
- Nếu muốn truyền biến vào script thì truyền vào $options->varObj
- Nếu muốn lưu entity sau khi chạy script thì truyền vào $option->save = '', 'SILENT', 'SKIP_ALL'.... Đầy đủ option xem tại https://docs.espocrm.com/development/orm/

1. runScript\current(string $script, stdClass $options = null): formula chạy script đưa vào với target entity là entity hiện tại, return entity Object.
2. runScript\target(string $entityType, string $id, string $script, stdClass $options = null):  formula chạy script với target entity và script đưa vào, return entity object
