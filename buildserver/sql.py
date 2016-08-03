import pymysql,traceback,os,json

PATH = os.path.dirname(os.path.abspath(__file__))+'/'
with open(PATH+'settings.json') as f:
	config = json.load(f)


#sql handler
class Sql:
	db = False
	lastRowId = -1
	def fetchOneAssoc(self,cur):
		data = cur.fetchone()
		if data == None:
			return None
		desc = cur.description
		ret = {}
		for (name,value) in zip(desc,data):
			ret[name[0]] = value
		print(ret)
		return ret
	def getDbCursor(self):
		try:
			self.db = pymysql.connect(
				host=config['sql']['server'],
				user=config['sql']['user'],
				password=config['sql']['passwd'],
				db=config['sql']['db'],
				unix_socket='/var/run/mysqld/mysqld.sock',
				charset='utf8',
				cursorclass=pymysql.cursors.DictCursor)
		except:
			try:
				self.db = pymysql.connect(
					host=config['sql']['server'],
					user=config['sql']['user'],
					password=config['sql']['passwd'],
					db=config['sql']['db'],
					charset='utf8',
					cursorclass=pymysql.cursors.DictCursor)
			except:
				try:
					self.db = pymysql.connect(
						host=config['sql']['server'],
						user=config['sql']['user'],
						passwd=config['sql']['passwd'],
						db=config['sql']['db'],
						unix_socket='/var/run/mysqld/mysqld.sock',
						charset='utf8',
						cursorclass=pymysql.cursors.DictCursor)
				except:
					self.db = pymysql.connect(
						host=config['sql']['server'],
						user=config['sql']['user'],
						passwd=config['sql']['passwd'],
						db=config['sql']['db'],
						charset='utf8',
						cursorclass=pymysql.cursors.DictCursor)
		return self.db.cursor()
	def query(self,q,p = []):
		try:
			cur = self.getDbCursor()
			cur.execute(q,tuple(p))
			self.lastRowId = cur.lastrowid
			
			self.db.commit()
			rows = []
			for row in cur:
				if row == None:
					break
				rows.append(row)
			cur.close()
			self.db.close()
			return rows
		except Exception as inst:
			traceback.print_exc()
			return False
	def insertId(self):
		return self.lastRowId
	def close(self):
		try:
			self.db.close()
		except:
			pass
