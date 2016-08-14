import pymysql,traceback,os,json

PATH = os.path.dirname(os.path.abspath(__file__))+'/'
with open(PATH+'settings.json') as f:
	config = json.load(f)


#sql handler
class Sql:
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
	def getDb(self):
		try:
			return pymysql.connect(
				host=config['sql']['server'],
				user=config['sql']['user'],
				password=config['sql']['passwd'],
				db=config['sql']['db'],
				unix_socket='/var/run/mysqld/mysqld.sock',
				charset='utf8',
				cursorclass=pymysql.cursors.DictCursor)
		except:
			try:
				return pymysql.connect(
					host=config['sql']['server'],
					user=config['sql']['user'],
					password=config['sql']['passwd'],
					db=config['sql']['db'],
					charset='utf8',
					cursorclass=pymysql.cursors.DictCursor)
			except:
				try:
					return pymysql.connect(
						host=config['sql']['server'],
						user=config['sql']['user'],
						passwd=config['sql']['passwd'],
						db=config['sql']['db'],
						unix_socket='/var/run/mysqld/mysqld.sock',
						charset='utf8',
						cursorclass=pymysql.cursors.DictCursor)
				except:
					return pymysql.connect(
						host=config['sql']['server'],
						user=config['sql']['user'],
						passwd=config['sql']['passwd'],
						db=config['sql']['db'],
						charset='utf8',
						cursorclass=pymysql.cursors.DictCursor)
		return False
		return self.db.cursor()
	def query(self,q,p = []):
		try:
			db = self.getDb()
			assert(db)
			cur = db.cursor()
			cur.execute(q,tuple(p))
			self.lastRowId = cur.lastrowid
			
			db.commit()
			rows = []
			for row in cur:
				if row == None:
					break
				rows.append(row)
			
			cur.close()
			db.close()
			return rows
		except Exception as inst:
			traceback.print_exc()
			return False
	def insertId(self):
		return self.lastRowId
