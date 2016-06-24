import os,sys
if os.geteuid() != 0:
	print('Sorry, but i need root!')
	sys.exit(1)

import json,pwd,grp,shutil,subprocess,traceback,time,threading,shlex,ntpath,server,re

PATH = os.path.dirname(os.path.abspath(__file__))+'/'
UID = 1000
GID = 1000
QUEUE = []

with open(PATH+'settings.json') as f:
	config = json.load(f)

def makeUnicode(s):
	try:
		return s.decode('utf-8')
	except:
		return s



class Chroot_jail:
	STATE_UNREADY = 0
	STATE_READY = 1
	STATE_RUNNING = 2
	STATE_DONE = 3
	state = 0
	sandbox_template_path = PATH+'sandbox/template'
	commands = []
	success = False
	mount_binds = ['random','urandom','null','zero']
	key = ''
	thread = None
	
	TYPE_BUILD = 0
	TYPE_EXAMIN = 1
	boxtype = 0
	
	def __init__(self,i,boxtype = TYPE_BUILD):
		self.i = i
		self.sandbox_path = PATH+'sandbox/box_'+str(i)
		self.state = self.STATE_UNREADY
		
	def demote_maketemplate(self):
		os.chroot(self.sandbox_template_path)
		os.chdir('/')
	def rmTemplate(self):
		if os.path.isdir(self.sandbox_template_path):
			shutil.rmtree(self.sandbox_template_path)
	def makeTemplate_userspace(self):
		os.chroot(self.sandbox_template_path)
		os.chdir('/build')
		
		os.setgid(GID) # set GID before UID so that we actually have permission
		os.setuid(UID)
		
		os.makedirs('/build/bin')
		os.makedirs('/build/.arduino15/packages')
		subprocess.call(['wget','https://github.com/Rodot/Gamebuino/archive/master.zip','-O','/tmp/gamebuino.zip'])
		subprocess.call(['unzip','/tmp/gamebuino.zip','-d','/tmp'])
		subprocess.call(['rm','/tmp/gamebuino.zip'])
		subprocess.call(['mv','/tmp/Gamebuino-master','/build/Arduino'])
	def makeTemplate(self):
		try:
			print('Sandbox template absent, building...')
			self.rmTemplate()
			
			# create chroot
			if subprocess.call(['mkarchroot',self.sandbox_template_path]+config['packages']) != 0:
				self.rmTemplate()
				return False
			# create the build user
			subprocess.call(['groupadd','build','-g',str(GID)],preexec_fn=self.demote_maketemplate)
			subprocess.call(['useradd','build','-d','/build','-M','-u',str(UID),'-g',str(GID)],preexec_fn=self.demote_maketemplate)
			
			
			# create arduino binary
			if os.path.exists(PATH+'sandbox/arduino.tar.xz'):
				os.remove(PATH+'sandbox/arduino.tar.xz')
			subprocess.call(['wget','https://www.arduino.cc/download.php?f=/arduino-1.6.4-linux64.tar.xz','-O',PATH+'sandbox/arduino.tar.xz'])
			subprocess.call(['tar','xpvf',PATH+'sandbox/arduino.tar.xz','-C',self.sandbox_template_path+'/usr/share'])
			subprocess.call(['mv',self.sandbox_template_path+'/usr/share/arduino-1.6.4',self.sandbox_template_path+'/usr/share/arduino'])
			
			
			subprocess.call(['wget','https://downloads.arduino.cc/tools/arduino-builder-linux64-1.3.14.tar.bz2','-O',PATH+'sandbox/arduino-builder.tar.bz2'])
			subprocess.call(['tar','jxvf',PATH+'sandbox/arduino-builder.tar.bz2','-C',self.sandbox_template_path+'/usr/share/arduino'])
			
			
			# copy makefiles
			shutil.copytree(PATH+'makefiles',self.sandbox_template_path+'/makefiles')
			
			
			# create dev mount points
			for t in self.mount_binds:
				open(self.sandbox_template_path+'/dev/'+t,'a').close()
				subprocess.call(['mount','--bind','/dev/'+t,self.sandbox_template_path+'/dev/'+t]) # we need the mounts while building
			
			# create build directory
			os.makedirs(self.sandbox_template_path+'/build',0o755)
			os.chown(self.sandbox_template_path+'/build',UID,GID)
			try:
				subprocess.call([':'],shell=True,preexec_fn=self.makeTemplate_userspace)
			except:
				for t in self.mount_binds:
					subprocess.call(['umount',self.sandbox_template_path+'/dev/'+t])
				raise
			
			
			for t in self.mount_binds:
				subprocess.call(['umount',self.sandbox_template_path+'/dev/'+t])
			return True
		except:
			self.rmTemplate()
			traceback.print_exc()
			return False
	def build_box(self,boxtype = 0):
		print('Building sandbox box_'+str(self.i)+' ...')
		self.boxtype = boxtype
		try:
			self.success = False
			if self.state == self.STATE_READY:
				return True
			if self.state != self.STATE_UNREADY:
				raise
			
			if not os.path.isdir(PATH+'sandbox/template'):
				if not self.makeTemplate():
					raise
			if os.listdir(PATH+'sandbox/template') == []:
				if not self.makeTemplate():
					raise
			self.destroy_box()
			
			os.mkdir(self.sandbox_path)
			
			if subprocess.call(['rsync','-a',self.sandbox_template_path+'/',self.sandbox_path]) != 0:
				raise
			
			self.state = self.STATE_READY
			self.commands = []
			
			self.thread = threading.Thread(target=self.run)
			return True
		except Exception as inst:
			print(inst)
			traceback.print_exc()
			self.doneQueue()
			return False
	def destroy_box(self):
		if os.path.isdir(self.sandbox_path):
			shutil.rmtree(self.sandbox_path)
		self.state = self.STATE_UNREADY
	def set_key(self,key):
		self.key = key
	def build_path(self,path):
		self.commands += [
			{
				'type':'exec',
				'fn':lambda:os.chdir(path)
			}
		]
	def build_includefiles(self,files):
		for f in files:
			if isinstance(f,str):
				self.commands += [
					{
						'type':'cmd',
						'cmd':['cp',f,'/build/bin/'+ntpath.basename(f)]
					}
				]
			else:
				self.commands += [
					{
						'type':'cmd',
						'cmd':['cp',f[0],'/build/bin/'+f[1]]
					}
				]
	def build_command(self,cmd):
		cmd = ['timeout',str(config['timeout'])]+shlex.split(cmd)
		self.commands += [
			{
				'type':'cmd',
				'cmd':cmd
			}
		]
	def build_makefile(self,ino,name):
		self.commands += [
			{
				'type':'cmd',
				'cmd':['cp','/makefiles/gamebuino.mk','Makefile']
			},
			{
				'type':'cmd',
				'cmd':['timeout',str(config['timeout']),'make','INO_FILE='+ino,'NAME='+name]
			}
		]
	
	def build_git(self,repo):
		self.commands += [
			{
				'type':'cmd',
				'cmd':['mkdir','repo']
			},
			{
				'type':'cmd',
				'cmd':['git','clone','--depth','1',repo,'repo']
			},
			{
				'type':'cmd',
				'shell':True,
				'cmd':'mv repo/* .'
			}
		]
	def examin(self):
		print('examining this thing....')
		inofile = ''
		inodirectory = ''
		name = ''
		include = []
		
		# we try to find the ino file by <name>/<name>.ino first
		for root, dirs, files in os.walk('.'):
			dirs[:] = [d for d in dirs if d not in ['Arduino','.arduino15','bin']]
			for f in files:
				if f[-4:] == '.ino':
					print('Found INO file',f)
					fname = f[:-4]
					if root[-len(fname):] == fname:
						inofile = f
						inodirectory = root
			if inofile != '':
				break
		
		# well, if we didn't find anything....let's start looking into the files themself!
		if inofile == '':
			for root, dirs, files in os.walk('.'):
				dirs[:] = [d for d in dirs if d not in ['Arduino','.arduino15','bin']]
				for f in files:
					if f[-4:] == '.ino':
						print('Found INO file',f)
						with open(root+'/'+f) as o:
							c = o.read()
							if re.search(r"void\s+setup\([^)]*\)\s*{",c) and re.search(r"void\s+loop\([^)]*\)\s*{",c):
								print('Found you sucker!')
								inofile = f
								inodirectory = root
								break
				if inofile != '':
					break
		
		#now search for the name and additional INF files to include!
		if inofile != '':
			for root, dirs, files in os.walk('.'):
				dirs[:] = [d for d in dirs if d not in ['Arduino','.arduino15','bin']]
				for f in files:
					ext = f[-4:]
					if ext == '.HEX' or ext == '.INF':
						name = f[:-4][:8].upper()
					if ext == '.INF':
						path = "../"*(len(os.path.split(inodirectory+'/'))-1)+root+'/'+f
						
						include.append(os.path.normpath(path))
		
		if name == '':
			name = inofile[:-4][:8].upper()
		inodirectory = os.path.normpath(inodirectory)
		
		with open('/build/.resp_code','w+') as f:
			f.write(str(int(inofile == '')))
		
		json_obj = {
			'ino_file':inofile,
			'path':inodirectory,
			'filename':name,
			'include':include
		}
		with open('/build/.results.json','w+') as f:
			f.write(json.dumps(json_obj))
	def build_examin(self):
		self.commands += [
			{
				'type':'exec',
				'fn':self.examin
			}
		]
	def demote(self):
		try:
			print('Running chroot ('+str(self.i)+')...')
			if self.state != self.STATE_RUNNING:
				return
			
			for t in self.mount_binds:
				subprocess.call(['mount','--bind','/dev/'+t,self.sandbox_path+'/dev/'+t])
			os.chroot(self.sandbox_path)
			os.chdir('/build')
			
			
			os.setgid(GID) # set GID before UID so that we actually have permission
			os.setuid(UID)
			
			self.success = True
			with open('/build/.output','w+') as o:
				for c in self.commands:
					o.write('Executing command '+str(c)+':\n')
					o.flush()
					if c['type'] == 'cmd':
						resp = subprocess.call(c['cmd'],shell=('shell' in c and c['shell']),stdout=o)
						with open('/build/.resp_code','w+') as f:
							f.write(str(resp))
						if resp != 0:
							break
					elif c['type'] == 'exec':
						c['fn']()
					o.write('\n\n\n')
					o.flush()
			
		except:
			traceback.print_exc()
			self.success = False
			self.state = self.STATE_DONE # just trash the chroot
	def doneQueue(self):
		if self.boxtype == self.TYPE_BUILD:
			QUEUE.append({
				'type':'done',
				'success':self.success,
				'jail':self,
				'key':self.key
			})
		elif self.boxtype == self.TYPE_EXAMIN:
			try:
				with open(self.sandbox_path+'/build/.results.json','r+') as f:
					json_data = json.load(f)
				QUEUE.append({
					'type':'done_examin',
					'success':self.success,
					'jail':self,
					'key':self.key,
					'ino_file':json_data['ino_file'],
					'filename':json_data['filename'],
					'path':json_data['path'],
					'include':json_data['include']
				})
			except:
				QUEUE.append({
					'type':'done_examin',
					'success':False,
					'jail':self,
					'key':self.key,
					'ino_file':'',
					'filename':'',
					'path':'',
					'include':[]
				})
		self.state = self.STATE_DONE # we need to trash the chroot, it may be unsafe!
	def run(self):
		if self.state != self.STATE_READY:
			self.doneQueue()
			return
		self.state = self.STATE_RUNNING
		outfile = PATH+'output/'+self.key
		try:
			os.lstat(outfile)
			os.remove(outfile)
		except:
			pass
		os.symlink('../sandbox/box_'+str(self.i)+'/build/.output',outfile)
		subprocess.call([':'],shell=True,preexec_fn=self.demote) # hack so that the parent isn't effected
		
		for t in self.mount_binds:
			subprocess.call(['umount',self.sandbox_path+'/dev/'+t])
		
		self.success = False
		try:
			with open(self.sandbox_path+'/build/.resp_code','r+') as f:
				self.success = f.read() == '0'
		except:
			self.success = False
		self.doneQueue()
		print('Done with box_'+str(self.i))

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
				if data['type'] == 'build':
					QUEUE.append(data)
				elif data['type'] == 'destroy':
					QUEUE.append(data)
				elif data['type'] == 'examin':
					QUEUE.append(data)
			except:
				traceback.print_exc()
		return True


if __name__ == '__main__':
	for f in ['sandbox','builds','input','output']:
		if not os.path.isdir(PATH+f):
			if os.path.exists(PATH+f):
				print('ERROR: '+PATH+f+' exists in the filesystem')
				sys.exit(1)
			os.makedirs(PATH+f)
	jails = []
	for i in range(config['max_jails']):
		jails.append(Chroot_jail(i))
	def getIdleJail():
		for j in jails:
			if j.state == j.STATE_UNREADY:
				return j
		return None
	srv = server.Server(PATH+'/socket.sock',0,ServerLink)
	srv.start()
	def sendAll(obj):
		for i in srv.inputHandlers:
			try:
				i.socket.sendall(bytes(json.dumps(obj)+'\n','utf-8'))
			except:
				traceback.print_exc()
	try:
		while True:
			time.sleep(5)
			try:
				if len(QUEUE) > 0: # we don't do a for-loop to make multi-threading more possible
					data = QUEUE.pop(0) # ofc we start with the oldest elements
					print('Queue: ',data)
					if data['type'] == 'build':
						j = getIdleJail()
						if not j:
							QUEUE.append(data) # we couldn't find a jail, let's try again later!
							continue
						j.build_box()
						j.set_key(data['build']['key'])
						if data['build']['type'] == 'git':
							j.build_git(data['build']['git'])
						if 'path' in data['build']:
							j.build_path(data['build']['path'])
						if 'makefile' in data['build']:
							j.build_makefile(data['build']['makefile']['ino'],data['build']['makefile']['name'])
						if 'include' in data['build']:
							j.build_includefiles(data['build']['include'])
						j.thread.start()
					elif data['type'] == 'done':
						if data['success']:
							outdir = PATH+'builds/'+data['jail'].key
							if os.path.isdir(outdir):
								shutil.rmtree(outdir)
							shutil.copytree(data['jail'].sandbox_path+'/build/bin/',outdir)
						outfile = PATH+'output/'+data['jail'].key
						try:
							os.lstat(outfile)
							os.remove(outfile)
						except:
							pass
						shutil.copyfile(data['jail'].sandbox_path+'/build/.output',outfile)
						data['jail'].destroy_box()
						sendAll({
							'type':'done',
							'success':data['success'],
							'key':data['jail'].key
						})
					elif data['type'] == 'examin':
						j = getIdleJail()
						if not j:
							QUEUE.append(data) # we couldn't find a jail, let's try again later!
							continue
						j.build_box(j.TYPE_EXAMIN)
						j.set_key(data['examin']['key'])
						if data['examin']['type'] == 'git':
							j.build_git(data['examin']['git'])
						j.build_examin()
						j.thread.start()
					elif data['type'] == 'done_examin':
						sendAll({
							'type':'done_examin',
							'success':data['success'],
							'key':data['jail'].key,
							'ino_file':data['ino_file'],
							'filename':data['filename'],
							'path':data['path'],
							'include':data['include']
						})
						data['jail'].destroy_box()
						QUEUE.append({
							'type':'destroy',
							'key':data['key']
						})
					elif data['type'] == 'destroy':
						outdir = PATH+'builds/'+data['key']
						if os.path.isdir(outdir):
							shutil.rmtree(outdir)
						outfile = PATH+'output/'+data['key']
						try:
							os.lstat(outfile)
							os.remove(outfile)
						except:
							pass
			except KeyboardInterrupt:
				raise KeyboardInterrupt
			except:
				traceback.print_exc()
	except KeyboardInterrupt:
		srv.stop()
		print('joining with jails...')
		for j in jails:
			try:
				j.thread.join()
			except:
				pass
