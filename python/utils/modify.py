# quick change of log.csv to log2.csv
# first line is header: date_time,priority,main_index,sub_index,message
# i need to prepend id, convert date_time to timestamp, that's all
import csv
import time

with open('log.csv', 'r') as f:
    reader = csv.reader(f)
    header = next(reader)  # skip header
    with open('log2.csv', 'w', newline='') as f2:
        writer = csv.writer(f2)
        writer.writerow(['id'] + header)  # write new header with prepended id
        for i, row in enumerate(reader, start=1):
            date_time = row[0]
            timestamp = int(time.mktime(time.strptime(date_time, '%Y-%m-%d %H:%M:%S')))
            row[0] = timestamp
            writer.writerow([i] + row)
