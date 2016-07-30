import socket,json,traceback,os,threading,sys,time,server,pymysql,shutil,hashlib
if os.geteuid() == 0:
	print('Do not ever run me as root!')
	sys.exit(1)
PATH = os.path.dirname(os.path.abspath(__file__))+'/'
with open(PATH+'settings.json') as f:
	config = json.load(f)

def hashFolder(path):
	hash = hashlib.sha1()
	for root, dirs, files in os.walk(path):
		for f in files:
			if f[-4:] != '.elf':
				with open(root+'/'+f,'rb') as fp:
					for chunk in iter(lambda: fp.read(4096), b''):
						hash.update(chunk)
	return hash.hexdigest()


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
				self.disconnect() # we won't reconnect here, we'll have errors in the main thread anyways
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
						print(data)
						qdata = sql.query("SELECT t1.`file`,t1.`type`,t1.`status`,t2.`public`,t1.`cmd_after` FROM `archive_queue` AS t1 INNER JOIN `archive_files` AS t2 ON t1.`file`=t2.`id` WHERE t1.`id`=%s",[int(data['key'])])
						if qdata:
							qdata = qdata[0]
							if qdata['type'] == 0 and qdata['status'] == 2: # make sure we are building this thing
								status = 0
								if data['success']:
									status = 3
									timestamp = str(int(time.time()))
									
									# copy the actual build files
									out_path = config['public_html']+'/files/gb1/'+str(qdata['file'])
									if not os.path.exists(out_path):
										os.makedirs(out_path)
									lastDir = '0'
									for d in os.listdir(out_path):
										if os.path.exists(out_path+'/'+d) and int(lastDir) < int(d):
											lastDir = d
									last_out = out_path+'/'+lastDir
									out_path += '/'+timestamp
									shutil.copytree(config['backend']+'/builds/'+str(data['key']),out_path)
									if lastDir != '0' and hashFolder(last_out) == hashFolder(out_path):
										status = 4
										shutil.rmtree(out_path)
									elif qdata['public']:
										sql.query("UPDATE `archive_files` SET `ts_updated`=FROM_UNIXTIME(%s) WHERE `id`=%s",[timestamp,int(qdata['file'])])
									else:
										sql.query("UPDATE `archive_files` SET `ts_updated`=FROM_UNIXTIME(%s),`ts_added`=FROM_UNIXTIME(%s),`public`=1 WHERE `id`=%s",[timestamp,timestamp,int(qdata['file'])])
									if qdata['cmd_after'] != -1:
										parseClientInput({
											'type':['build','examin'][qdata['cmd_after']],
											'fid':qdata['file']
										})
								output = ''
								try:
									with open(config['backend']+'/output/'+str(data['key']),'r') as f:
										output = f.read()
								except:
									output = ''
								sql.query("UPDATE `archive_queue` SET `status`=%s,`output`=%s WHERE `id`=%s",[status,output,int(data['key'])])
						self.send({
							'type':'destroy',
							'key':data['key']
						})
					elif data['type'] == 'done_examin':
						print(data)
						
						qdata = sql.query("SELECT t1.`file`,t1.`type`,t1.`status`,t1.`cmd_after` FROM `archive_queue` AS t1 INNER JOIN `archive_files` AS t2 ON t1.`file`=t2.`id` WHERE t1.`id`=%s",[int(data['key'])])
						if qdata:
							qdata = qdata[0]
							if qdata['type'] == 1 and qdata['status'] == 2: # we were actually examining it, so everything is fine
								if data['success']:
									sql.query("UPDATE `archive_files` SET `build_path`=%s,`build_command`=%s,`build_makefile`=1,`build_filename`=%s,`build_movepath`=%s WHERE `id`=%s",[data['path'],'make INO_FILE='+data['ino_file']+' NAME=%name%',data['filename'],data['movepath'],qdata['file']])
									if qdata['cmd_after'] != -1:
										parseClientInput({
											'type':['build','examin'][qdata['cmd_after']],
											'fid':qdata['file']
										})
								else:
									sql.query("UPDATE `archive_files` SET `build_command`='ERROR' WHERE `id`=%s",[qdata['file']])
								sql.query("DELETE FROM `archive_queue` WHERE `id`=%s",[int(data['key'])])
						print(qdata)
				except:
					traceback.print_exc()
		self.disconnect()

def parseClientInput(data,key = '',socket = False):
	print('>> ',data)
	success = False
	if data['type'] == 'build':
		fdata = sql.query("SELECT `id`,`file_type`,`git_url`,`build_path`,`build_command`,`build_makefile`,`build_filename`,`build_movepath` FROM `archive_files` WHERE `id`=%s",[int(data['fid'])])
		if fdata:
			fdata = fdata[0]
			if key:
				sql.query("UPDATE `archive_queue` SET `status`=2 WHERE `id`=%s",[int(key)])
			else:
				sql.query("INSERT INTO `archive_queue` (`file`,`type`,`status`,`output`) VALUES (%s,0,2,'')",[fdata['id']])
				key = str(sql.insertId())
			
			if fdata['file_type'] <= 1:
				print(key)
				fdata['build_command'] = fdata['build_command'].replace('%name%',fdata['build_filename'])
				obj = {
					'type':'build',
					'build':{
						'key':key,
						'path':fdata['build_path'],
						'command':fdata['build_command']
					}
				}
				if fdata['build_makefile']:
					obj['build']['makefile'] = 'gamebuino.mk'
				if fdata['build_movepath']:
					obj['build']['movepath'] = fdata['build_movepath']
				if fdata['build_command'] != '':
					if fdata['file_type'] == 0:
						print('zip file')
						zipfile = config['public_html']+'/uploads/zip/'+str(fdata['id'])+'.zip'
						if os.path.isfile(zipfile):
							shutil.copyfile(zipfile,config['backend']+'/input/'+key+'.zip')
							obj['build']['type'] = 'zip'
							
							sock.send(obj)
							success = True
					elif fdata['file_type'] == 1:
						print('git stuff')
						obj['build']['type'] = 'git'
						obj['build']['git'] = fdata['git_url']
						
						sock.send(obj)
						success = True
			if success and socket:
				socket.sendall(bytes(json.dumps({
					'id':int(key)
				})+'\n','utf-8'));
			
	elif data['type'] == 'examin':
		fdata = sql.query("SELECT `id`,`file_type`,`git_url` FROM `archive_files` WHERE `id`=%s",[int(data['fid'])])
		if fdata:
			fdata = fdata[0]
			
			if key:
				sql.query("UPDATE `archive_queue` SET `status`=2 WHERE `id`=%s",[int(key)])
			else:
				sql.query("INSERT INTO `archive_queue` (`file`,`type`,`status`,`output`) VALUES (%s,1,2,'')",[fdata['id']])
				key = str(sql.insertId())
			print(key)
			if fdata['file_type'] <= 1:
				if fdata['file_type'] == 0:
					print('zip file')
					zipfile = config['public_html']+'/uploads/zip/'+str(fdata['id'])+'.zip'
					if os.path.isfile(zipfile):
						shutil.copyfile(zipfile,config['backend']+'/input/'+key+'.zip')
						sock.send({
							'type':'examin',
							'examin':{
								'type':'zip',
								'key':key
							}
						})
						success = True;
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
						success = True
			if success:
				sql.query("UPDATE `archive_files` SET `build_command`='DETECTING' WHERE `id`=%s",[fdata['id']])
	if success:
		if 'cmd_after' in data:
			sql.query("UPDATE `archive_queue` SET `cmd_after`=%s WHERE `id`=%s",[int(data['cmd_after']),int(key)])
	elif key:
		sql.query("DELETE FROM `archive_queue` WHERE `id`=%s",[int(key)])

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
				parseClientInput(data,socket = self.socket)
			except:
				traceback.print_exc()
		return True

if __name__ == '__main__':
	sock = SocketConnection(config['backend']+'/socket.sock')
	sock.start()
	
	srv = server.Server(PATH+'/socket.sock',0,ServerLink)
	srv.start()
	res = sql.query("SELECT `id`,`file`,`type` FROM `archive_queue` WHERE `status`=1") # get the queued things from when we were offline
	for r in res:
		print('Old Command: ',r)
		parseClientInput({
			'type':['build','examin'][r['type']],
			'fid':r['file']
		},str(r['id']))
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
