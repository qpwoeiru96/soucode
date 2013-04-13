#!/usr/bin/python
#encoding=utf-8
import sys
import MySQLdb
import pymssql
import xml.etree.ElementTree as ElementTree
import re

##############################################
 # 类型列表
##############################################
TYPE_INT     = type(1)
TYPE_FLOAT   = type(1.0)
TYPE_STR     = type('')
TYPE_UNICODE = type(u'')
TYPE_NONE    = type(None)

##############################################
 # SQL语句变量表
##############################################
SQL_VARIABLES = {}

def _replace_sql_variable(matches):
  if(matches.group(1) in SQL_VARIABLES):
		return unicode(SQL_VARIABLES[matches.group(1)])
	else:
		return matches.group(0)

def replace_sql_variable(sql):
	if type(sql) == TYPE_UNICODE or type(sql) == TYPE_STR:
		return re.sub('\[%([a-zA-Z0-9_-]+)\]', _replace_sql_variable, sql)
	else:
		return ''


##############################################
 #
 # 对String Param进行过滤[非二进制安全]
 #
 # @param unicode uni_str Unicode类型的字符串
 # @return unicode 过滤后的字符串
 #
##############################################
def escape_string(uni_str):

	#if( type(uni_str) != TYPE_UNICODE ):
	#	print 'Warning: not safety, please encode to unicode.'

	char_table = {
		'\\' : '\\\\',
		'\'' : '\\\'',
		'"'  : '\\"',
		'\n' : '\\n',
		'\r' : '\\r',
		'\0' : '\\0'
	}

	uni_res = ''

	for i in range(0, len(uni_str)):
		if( uni_str[i] in char_table):
			uni_res += char_table[uni_str[i]];
		else:
			uni_res += uni_str[i];

	return uni_res;

##############################################
 #
 # 对value进行预处理
 #
##############################################
def pretreat_value(value):

	obj_type = type(value)

	if obj_type == TYPE_INT or obj_type == TYPE_FLOAT:
		return str(value)
	elif obj_type == TYPE_UNICODE or obj_type == TYPE_STR:
		return "'" + escape_string(value) + "'"
	elif obj_type == TYPE_NONE:
		return 'NULL'
	else:
		return "'" + escape_string(str(value)) + "'"

##############################################
 #
 # 日志记录 (尚未实现)
 #
##############################################
def log(log_message):
	pass

##############################################
 #
 # 读取配置文件
 #
##############################################
def read_config(file):

	try:
		config = { 'source' : {}, 'target' : {} }
		fileds = ['type', 'host', 'port', 'user', 'password', 'database', 'charset', 'sql', 'preload'] #字段列表
		xml    = ElementTree.parse(file).getroot()

		for y in ['source', 'target']:
			tmp = xml.find(y)
			for x in fileds:
				config[y][x] = tmp.find(x).text

		config['source']['preload'] = replace_sql_variable(config['source']['preload'])
		config['target']['preload'] = replace_sql_variable(config['target']['preload'])

		return config

	except:
		print 'Error: read config failed.'
		exit(-1)


class Transfer:

	def __init__(self, config):
		self.config = config
		self._conn()
		self._preload()
		pass

	def _conn(self):
		pass

	def _preload(self):
		pass

	def execute(self, sql):
		
		try:
			self.cur.execute(sql.encode(self.config['charset']))
			return self.cur
		except:
			print 'ERROR: execute [%s] failed'%(sql)
			print sys.exc_info()
			exit(-1)	

	def get_sql(self):
		return replace_sql_variable(self.config['sql'])

	def get_data(self):
		sql = self.get_sql()
		cur = self.execute(sql)
		for x in cur:
			yield x

	def insert(self, data):

		run_sql = self.get_sql()
		
		for x in data:
			insert_sql = []
			for y in x:
				insert_sql.append(pretreat_value(y));
			insert_sql = ' , '.join(insert_sql)
			insert_sql = run_sql.replace('[%STR%]', insert_sql)			
			yield insert_sql

	def _preload(self):

		if len(self.config['preload'].strip()) > 6:
						
			cur = self.execute(self.config['preload'])
			data = cur.fetchone()

			for i in range(0, len(cur.description)):
				key = cur.description[i][0]
				val = data[i]
				SQL_VARIABLES[unicode(key)] = val


class SQLSERVER(Transfer):

	def __init__(self, config):

		self.config = config
		self.conn   = self._conn()		
		self.cur    = self.conn.cursor()
		self.conn.autocommit(True)
		self._preload();

	def _conn(self):

		try:
			return pymssql.connect(
				host          = self.config['host'] + ':' + self.config['port'],
				user          = self.config['user'],
				password      = self.config['password'],
				database      = self.config['database'],
				charset       = self.config['charset'],
				login_timeout = 3,
				as_dict       = False
			)
		except:
			print 'ERROR: SQL Server connect failed'
			exit(-1)

class MYSQL(Transfer):

	def __init__(self, config):
		self.config = config
		self.conn = self._conn()		
		self.cur  = self.conn.cursor()
		self.conn.autocommit(True)
		self._preload();

	def _conn(self):
		try:
			return MySQLdb.connect(
				user    = self.config['user'],
				passwd  = self.config['password'],
				host    = self.config['host'],
				db      = self.config['database'],
				port    = int(self.config['port']),
				charset = self.config['charset']
			)
		except:
			print 'Error: MySQL connect failed'
			exit(-1)
	

def main():

	if( len(sys.argv) < 2):

		print "#######################################################"
		print " # MySQl & SQL Server Data Sync @ver: 2.0"
		print " #"
		print " # Usage:  M2M_Data_Transfer.py config.xml args..."
		print " # Author: Qpwoeiru96@gmail.com 2013-03-21"
		print " # Link:   Http://sou.la/blog/IT/M2M_Data_Transfer"
		print "#######################################################"
		exit(-1)

	# 预处理在参数里面的脚本变量
	if( len(sys.argv) > 2):
		for i in range(2, len(sys.argv)):
			SQL_VARIABLES[unicode(i-1)] = sys.argv[i];

	config = read_config(sys.argv[1])

	source = globals()[config['source']['type']](config['source'])	
	target = globals()[config['target']['type']](config['target'])
	
	i = 0;
	for x in target.insert(source.get_data()):
		target.execute(x)
		i += 1;

	print 'execute %s sql.'%(i);

if __name__ == '__main__':
	main()
else:
	print 'Not allowed import.'
	exit(-1)
