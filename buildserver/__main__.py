import os,sys,traceback,server,sandbox,json,time,hashlib,lxc,shutil
from sql import Sql

if os.geteuid() == 0:
	print('Do not ever run me as root!')
	sys.exit(1)
PATH = os.path.dirname(os.path.abspath(__file__))+'/'

QUEUE = []

with open(PATH+'settings.json') as f:
	config = json.load(f)

sql = Sql()

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


class Box(sandbox.Box):
	def done_examin(self,data):
		qdata = sql.query("SELECT t1.`file`,t1.`type`,t1.`status`,t1.`cmd_after` FROM `archive_queue` AS t1 INNER JOIN `archive_files` AS t2 ON t1.`file`=t2.`id` WHERE t1.`id`=%s",[int(self.key)])
		if qdata:
			qdata = qdata[0]
			if qdata['type'] == 1 and qdata['status'] == 2: # we were actually examining it, so everything is fine
				if self.success:
					sql.query("UPDATE `archive_files` SET `build_path`=%s,`build_command`=%s,`build_makefile`=1,`build_filename`=%s,`build_movepath`=%s WHERE `id`=%s",[data['path'],'make INO_FILE='+data['ino_file']+' NAME=%name%',data['filename'],data['movepath'],qdata['file']])
					if qdata['cmd_after'] != -1:
						parseClientInput({
							'type':['build','examin'][qdata['cmd_after']],
							'fid':qdata['file']
						})
				else:
					sql.query("UPDATE `archive_files` SET `build_command`='ERROR' WHERE `id`=%s",[qdata['file']])
				sql.query("DELETE FROM `archive_queue` WHERE `id`=%s",[int(self.key)])
	def done_build(self,sandbox_path):
		qdata = sql.query("SELECT t1.`file`,t1.`type`,t1.`status`,t2.`public`,t1.`cmd_after` FROM `archive_queue` AS t1 INNER JOIN `archive_files` AS t2 ON t1.`file`=t2.`id` WHERE t1.`id`=%s",[int(self.key)])
		if qdata:
			qdata = qdata[0]
			if qdata['type'] == 0 and qdata['status'] == 2: # make sure we are building this thing
				status = 0
				if self.success:
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
					
					if lastDir != '0' and hashFolder(last_out) == hashFolder(sandbox_path+'/build/bin'):
						status = 4
					else:
						shutil.copytree(sandbox_path+'/build/bin',out_path)
						if qdata['public']:
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
					with open(sandbox_path+'/build/.output','r') as f:
						output = f.read()
				except:
					output = ''
				sql.query("UPDATE `archive_queue` SET `status`=%s,`output`=%s WHERE `id`=%s",[status,output,int(self.key)])
	def doneQueue(self):
		try:
			c = lxc.Container('gamebuino-buildserver-'+str(self.i))
			assert(c.defined)
			sandbox_path = c.get_config_item('lxc.rootfs')
			
			
			if self.boxtype == self.TYPE_BUILD:
				if self.success:
					self.success = False
					for f in os.listdir(sandbox_path+'/build/bin'):
						if f[-4:].lower() == '.hex':
							self.success = True
							break
					print('buildbox')
					print(self.success)
				self.done_build(sandbox_path)
			elif self.boxtype == self.TYPE_EXAMIN:
				try:
					if self.success:
						with open(sandbox_path+'/build/.results.json','r') as f:
							json_data = json.load(f)
				except:
					json_data = {}
					self.success = False
				print(self.success)
				print(json_data)
				self.done_examin(json_data)
		except:
			traceback.print_exc()
		return True # we want to trash the sandbox!

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
							obj['build']['type'] = 'zip'
							obj['build']['zip'] = zipfile
							
							QUEUE.append(obj)
							success = True
					elif fdata['file_type'] == 1:
						print('git stuff')
						obj['build']['type'] = 'git'
						obj['build']['git'] = fdata['git_url']
						
						QUEUE.append(obj)
						success = True
			if success and socket:
				try:
					socket.sendall(bytes(json.dumps({
						'id':int(key)
					})+'\n','utf-8'))
				except:
					pass
			
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
						QUEUE.append({
							'type':'examin',
							'examin':{
								'type':'zip',
								'zip':zipfile,
								'key':key
							}
						})
						success = True;
				elif fdata['file_type'] == 1:
					print('git stuff')
					if fdata['git_url'] != '':
						QUEUE.append({
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
	elif data['type'] == 'destroy_template':
		QUEUE.append({
			'type':'destroy_template'
		})
		return
	if success:
		if 'cmd_after' in data:
			sql.query("UPDATE `archive_queue` SET `cmd_after`=%s WHERE `id`=%s",[int(data['cmd_after']),int(key)])
	elif key:
		sql.query("DELETE FROM `archive_queue` WHERE `id`=%s",[int(key)])


if __name__ == '__main__':
	if not os.path.isdir(PATH+'output'):
		if os.path.exists(PATH+'output'):
			print('ERROR: '+PATH+'output exists in the filesystem')
			sys.exit(1)
		os.makedirs(PATH+'output')
	
	# if we were in the middle of creating the template we kill it just to be sure
	try:
		if os.path.exists(PATH+'mktemplate.lock'):
			Box.rmTemplate(Box)
			os.remove(PATH+'mktemplate.lock')
	except:
		traceback.print_exc()
		pass
	
	boxes = []
	for i in range(config['max_sandboxes']):
		b = Box()
		b.destroy_box()
		boxes.append(b)
	
	noboxes = False
	def getIdleBox():
		if noboxes:
			return None
		for b in boxes:
			if b.state == b.STATE_READY:
				return b
		return None
	
	print('Listening to socket file...')
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
			time.sleep(5)
			try:
				if len(QUEUE) > 0: # we don't do a for-loop to make multi-threading more possible
					data = QUEUE.pop(0) # ofc we start with the oldest elements
					print('Queue: ',data)
					if data['type'] == 'build':
						b = getIdleBox()
						if not b:
							QUEUE.append(data) # we couldn't find a jail, let's try again later!
							continue
						b.setBoxType(b.TYPE_BUILD)
						b.set_key(data['build']['key'])
						if data['build']['type'] == 'zip':
							b.build_zip(data['build']['zip'])
						elif data['build']['type'] == 'git':
							b.build_git(data['build']['git'])
						if 'path' in data['build']:
							b.build_path(data['build']['path'])
						if 'movepath' in data['build']:
							b.build_movepath(data['build']['movepath'])
						if 'makefile' in data['build']:
							b.build_makefile(data['build']['makefile'])
						b.build_command(data['build']['command'])
						if 'include' in data['build']:
							b.build_includefiles(data['build']['include'])
						b.thread.start()
					elif data['type'] == 'examin':
						b = getIdleBox()
						if not b:
							QUEUE.append(data)
							continue
						b.setBoxType(b.TYPE_EXAMIN)
						b.set_key(data['examin']['key'])
						if data['examin']['type'] == 'zip':
							b.build_zip(data['examin']['zip'])
						elif data['examin']['type'] == 'git':
							b.build_git(data['examin']['git'])
						
						b.build_examin()
						b.thread.start()
					elif data['type'] == 'destroy_template':
						noboxes = True
						
						boxes_busy = False
						for b in boxes:
							if b.state != b.STATE_READY:
								boxes_busy = True
								break
						if boxes_busy:
							QUEUE.append(data)
							continue
						Box.rmTemplate(Box)
						for b in boxes:
							b.destroy_box()
						
						noboxes = False
			except KeyboardInterrupt:
				raise KeyboardInterrupt
			except:
				traceback.print_exc()
	except KeyboardInterrupt:
		print('KeyboardInterrupt')
		srv.stop()
		print('joining with boxes...')
		for b in boxes:
			try:
				b.thread.join()
			except:
				pass
	
	sys.exit()
	box = Box()
	box.set_key('testbox')
	box.build_box()
	print('Done building the box!')
	box.build_git('https://github.com/Sorunome/blockdude-gamebuino')
	box.build_path('blockdude')
	box.build_makefile('gamebuino.mk')
	box.build_command('make INO_FILE=blockdude.ino NAME=BLOKDUDE')
	box.run()
	
