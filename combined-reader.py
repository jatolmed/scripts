#!/usr/bin/python3
import sys
import csv
import readline
from datetime import datetime
import requests

if len(sys.argv) < 2:
    print(f"Usage {sys.argv[0]} <log-file>")
    exit(127)
log_filename = sys.argv[1]

logs = []

class Log:
    def __init__(self, row):
        self.ip = row[0]
        self.user = row[2]
        self.date = datetime.strptime(f"{row[3]} {row[4]}", "[%d/%b/%Y:%H:%M:%S %z]")
        self.request = row[5]
        self.status = row[6]
        self.size = row[7]
        self.referer = row[8]
        self.agent = row[9]
    def print(self):
        print(f"{self.ip} {self.user} {self.date.strftime('%Y-%m-%dT%H:%M:%S')} \"{self.request}\" {self.status} {self.size} \"{self.referer}\", \"{self.agent}\"")

class FilterItem:
    def __init__(self, affirmative, value):
        self.affirmative = affirmative
        self.value = value

class Filter:
    def __init__(self, data):
        self.ip = data['ip']
        self.user = data['user']
        self.date = data['date']
        self.request = data['request']
        self.status = data['status']
        self.size = data['size']
        self.referer = data['referer']
        self.agent = data['agent']
    def check(self, log):
        matches = True
        if self.ip != None:
            matches = matches and ((self.ip.affirmative and self.ip.value == log.ip) or (not self.ip.affirmative and self.ip.value != log.ip))
        if self.user!= None:
            matches = matches and ((self.user.affirmative and self.user.value.lower() in log.user.lower()) or (not self.user.affirmative and self.user.value.lower() not in log.user.lower()))
        if self.date != None:
            matches = matches and ((self.date.affirmative and self.date.value <= log.date.strftime("%Y-%m-%dT%H:%M:%S")) or (not self.date.affirmative and self.date.value >= log.date.strftime("%Y-%m-%dT%H:%M:%S")))
        if self.request != None:
            matches = matches and ((self.request.affirmative and self.request.value.lower() in log.request.lower()) or (not self.request.affirmative and self.request.value.lower() not in log.request.lower()))
        if self.status != None:
            matches = matches and ((self.status.affirmative and self.status.value == log.status) or (not self.status.affirmative and self.status.value != log.status))
        if self.size != None:
            matches = matches and ((self.size.affirmative and self.size.value == log.size) or (not self.size.affirmative and self.size.value != log.size))
        if self.referer != None:
            matches = matches and ((self.referer.affirmative and self.referer.value.lower() in log.referer.lower()) or (not self.referer.affirmative and self.referer.value.lower() not in log.referer.lower()))
        if self.agent != None:
            matches = matches and ((self.agent.affirmative and self.agent.value.lower() in log.agent.lower()) or (not self.agent.affirmative and self.agent.value.lower() not in log.agent.lower()))
        return matches

def load(filename):
    global logs
    with open(filename, "r") as log_file:
        logs = []
        reader = csv.reader(log_file, delimiter = " ")
        for row in reader:
            logs += [Log(row)]

def parse_command(command):
    load(log_filename)
    components = command.split(" ")
    if components[0] == "all":
        for log in logs:
            log.print()
    elif components[0] == "filter":
        parse_filter(components[1:])
    elif components[0] == "group":
        parse_group(components[1:], logs)
    elif components[0] == "locate":
        if len(components) > 1:
            locate(components[1])

def parse_filter(components):
    length = len(components)
    data = {
        'ip': None,
        'user': None,
        'date': None,
        'request': None,
        'status': None,
        'size': None,
        'referer': None,
        'agent': None,
    }
    affirmative = True
    for idx, component in enumerate(components):
        if component == 'not':
            affirmative = False
        elif component == 'group':
            components = components[idx:]
            break
        elif component in data and idx + 1 < length:
            data[component] = FilterItem(affirmative, components[idx + 1])
            affirmative = True
    applying = Filter(data)
    filtered = []
    for log in logs:
        if applying.check(log):
            filtered += [log]
    if len(components) > 0 and components[0] == 'group':
        parse_group(components[1:], filtered)
    else:
        for log in filtered:
            log.print()

def parse_group(components, subset):
    available_coordinates = ['ip', 'user', 'date', 'request', 'status', 'size', 'referer', 'agent']
    components = list(filter(lambda c: c in available_coordinates, components))
    root = {
        'depth': len(components) + 1,
        'children': [],
        'subset': subset
    }
    build_group_tree(components, 0, root)
    print_group_tree(root)

def build_group_tree(components, index, tree):
    length = len(components)
    if index < length:
        values = {}
        for log in tree['subset']:
            value = getattr(log, components[index])
            if value not in values:
                values[value] = []
            values[value] += [log]
        for value in values:
            current = {
                'parent': tree,
                'value': value,
                'depth': length - index,
                'children': [],
                'subset': values[value]
            }
            build_group_tree(components, index + 1, current)
            tree['children'] += [current]

def print_group_tree(tree):
    if tree['depth'] <= 1:
        print_group_tree_node(tree)
    else:
        for child in tree['children']:
            print_group_tree(child)

def print_group_tree_node(leaf):
    components = []
    node = leaf
    while 'parent' in node:
        components += [node]
        node = node['parent']
    components.reverse()
    for component in components:
        print(component['value'], end = " ")
    print(len(leaf['subset']))

def locate(ip):
    resp = requests.get(f"https://ipinfo.io/{ip}/json")
    print(resp.content.decode('UTF-8'))

command = "all"
while command != "quit":
    parse_command(command)
    try:
        command = input("\033[32;1m> \033[94;1m")
        print("\033[0m", end="")
        readline.add_history(command)
    except:
        command = "quit"
        print("\033[0m")
