import socket,json,traceback,os,threading,sys,time,server,pymysql
if os.geteuid() == 0:
	print('Do not ever run me as root!')
	sys.exit(1)
PATH = os.path.dirname(os.path.abspath(__file__))+'/'
with open(PATH+'settings.json') as f:
	config = json.load(f)

def makeUnicode(s):
	try:
		return s.decode('utf-8')
	except:
		return s

#sql handler
class Sql:
	db = False
	lastRowId = -1
	def __init__(self):
		global config
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
		global config
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
sql = Sql()

class SocketConnection(threading.Thread):
	sockfile = ''
	connected = False
	s = False
	stopnow = False
	sendQueue = []
	def __init__(self,sockfile):
		threading.Thread.__init__(self)
		self.sockfile = sockfile
	def send(self,obj):
		if not self.connected:
			self.sendQueue.append(obj)
			return
		try:
			self.s.sendall(bytes(json.dumps(obj)+'\n','utf-8'))
		except:
			if not self.stopnow:
				self.sendQueue.append(obj)
				self.disconnect()
				self.connect()
	def connect(self):
		print('Connecting to socket '+self.sockfile+'...')
		while not self.stopnow: # python doesn't optimize tail recursion, we don't want that!
			try:
				self.s = socket.socket(socket.AF_UNIX,socket.SOCK_STREAM)
				self.s.settimeout(5)
				self.s.connect(self.sockfile)
				self.connected = True
				for s in self.sendQueue:
					self.send(s)
				self.sendQueue = []
				return
			except socket.error as e:
				print('Failed, retrying in five seconds')
				time.sleep(5)
	def disconnect(self):
		try:
			self.s.close()
		except:
			pass
		self.connected = False
	def quit(self):
		self.stopnow = True
	def run(self):
		self.connect()
		readbuffer = ''
		while not self.stopnow:
			try:
				readbuffer = makeUnicode(self.s.recv(1024))
			except socket.timeout:
				pass
			except socket.error as e:
				print(e)
				self.disconnect()
				self.connect()
			temp = readbuffer.split('\n')
			readbuffer = temp.pop()
			for line in temp:
				try:
					data = json.loads(line)
					if data['type'] == 'done':
						# TODO: copy&stuff
						self.send({
							'type':'destroy',
							'key':data['key']
						})
					elif data['type'] == 'done_examin':
						print(data)
						
						qdata = sql.query("SELECT t1.`file`,t1.`type`,t1.`status` FROM `archive_queue` AS t1 INNER JOIN `archive_files` AS t2 ON t1.`file`=t2.`id` WHERE t1.`id`=%s",[int(data['key'])])
						if qdata:
							qdata = qdata[0]
							if qdata['type'] == 1: # we were actually examining it, so everything is fine
								if data['success']:
									sql.query("UPDATE `archive_files` SET `build_path`=%s,`build_command`=%s,`build_makefile`=1,`build_filename`=%s WHERE `id`=%s",[data['path'],'make INO_FILE='+data['ino_file']+' NAME=%name%',data['filename'],qdata['file']])
								else:
									sql.query("UPDATE `archive_files` SET `build_command`='ERROR' WHERE `id`=%s",[qdata['file']])
								sql.query("DELETE FROM `archive_queue` WHERE `id`=%s",[int(data['key'])])
						print(qdata)
				except:
					traceback.print_exc()
		self.disconnect()

class ServerLink(server.ServerHandler):
	readbuffer = ''
	def recieve(self):
		try:
			data = makeUnicode(self.socket.recv(1024))
			if not data: # EOF
				return False
			self.readbuffer += data
		except:
			traceback.print_exc()
			return False
		temp = self.readbuffer.split('\n')
		self.readbuffer = temp.pop()
		for line in temp:
			try:
				data = json.loads(line)
				print('>>',data)
				if data['type'] == 'examin':
					fdata = sql.query("SELECT `id`,`file_type`,`git_url` FROM `archive_files` WHERE `id`=%s",[int(data['fid'])])
					if fdata:
						fdata = fdata[0]
						if fdata['file_type'] > 1:
							continue
						
						sql.query("INSERT INTO `archive_queue` (`file`,`type`,`status`,`output`) VALUES (%s,1,2,'')",[fdata['id']])
						key = str(sql.insertId())
						print(key)
						success = False
						
						if fdata['file_type'] == 0:
							print('zip file')
						elif fdata['file_type'] == 1:
							print('git stuff')
							if fdata['git_url'] != '':
								sock.send({
									'type':'examin',
									'examin':{
										'type':'git',
										'key':key,
										'git':fdata['git_url']
									}
								})
								sql.query("UPDATE `archive_files` SET `build_command`='DETECTING' WHERE `id`=%s",[fdata['id']])
								success = True
						if not success:
							sql.query("DELETE FROM `archive_queue` WHERE `id`=%s",[int(key)])
			except:
				traceback.print_exc()
		return True

if __name__ == '__main__':
	sock = SocketConnection(PATH+config['backend']+'/socket.sock')
	sock.start()
	#sock.send({
	#	'type':'examin',
	#	'examin':{
	#		'type':'git',
	#		'key':'asdf',
	#		'git':'git://github.com/Sorunome/blockdude-gamebuino.git',
	#		'path':'blockdude',
	#		'makefile':{
	#			'ino':'blockdude.ino',
	#			'name':'BLOKDUDE'
	#		},
	#		'include':['../BLOKDUDE.INF']
	#	}
	#})
	srv = server.Server(PATH+'/socket.sock',0,ServerLink)
	srv.start()
	try:
		while True:
			time.sleep(30)
	except KeyboardInterrupt:
		srv.stop()
		sock.stopnow = True
		try:
			sock.join()
		except:
			pass
