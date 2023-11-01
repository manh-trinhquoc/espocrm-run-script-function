## Overview

- Hàm formula chạy 1 đoạn script formula bất kỳ
- Lưu ý nguy cơ recurrsive call chính nó khi trong script cần chạy chứa hàm này

## Các hàm custom formula
- Hàm nhận đầu vào là entityType, id và formula script
1. runScript\current(string $script): formula chạy script đưa vào với target entity là entity hiện tại.
2. runScript\target(string $entityType, string $id, string $script):  formula chạy script với target entity và script đưa vào